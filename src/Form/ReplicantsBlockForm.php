<?php

namespace Drupal\replicants\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\replicate\Replicator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class ReplicateNodeBlockForm.
 *
 * @package Drupal\replicate_node_block\Form
 */
class ReplicantsBlockForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;
  
  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilder
   */
  protected $entityFormBuilder;
  
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, EntityFormBuilder $entity_form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('entity.form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'replicants_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form = [];
    if ($node != NULL && $nid = $node->id()) {
      $node_form = $this->entityFormBuilder->getForm($node, 'edit');
      $form['node_id'] = [
        '#type' => 'hidden',
        '#value' => $nid
      ];
      // @todo: figure out why we cant just set $form['title'] = $node_form['title]
      $form['title'] = [
        '#type' => 'textfield',
        '#default_value' => $node->getTitle(),
        '#title' => 'Title',
      ];
      $node_type = NodeType::load($node->getType());
      if ($node_type->getThirdPartySetting('workbench_moderation', 'enabled')) {
        $form['draft'] = [
          '#title' => 'New Draft',
          '#type' => 'checkbox',
          '#default_value' => 1
        ];
      }
      $form['save'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save as Copy'),
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Load the original node.
    $nid = $form_state->getValue('node_id');
    $node = Node::load($nid);
    $node_title = $node->getTitle();
    unset($node);
    // Create the replicant node.
    $replicator = new Replicator($this->entityTypeManager, $this->eventDispatcher);
    if ($replicant = $replicator->replicateByEntityId('node', $nid)) {
      // Concatenate url for the replicant node redirect.
      $url = '/node/' . $replicant->id();
      // Set title for replicant node.
      $title = $form_state->getValue('title');
      $replicant->setTitle($title);
      // Set moderation state for replicant node.
      if ($form_state->getValue('draft')) {
        // Create a new draft and append to our redirect.
        $replicant->moderation_state->target_id = 'draft';
        $url = $url . '/latest';
      }
      $replicant->save();
      // Send a friendly message.
      $message = $this->t('"@nodeTitle" [@nodeId] has been copied as "@replicantTitle" [@replicantId].', [
        '@nodeTitle' => $node_title,
        '@nodeId' => $nid,
        '@replicantTitle' => $replicant->getTitle(),
        '@replicantId' => $replicant->id(),
      ]);
      drupal_set_message($message, 'status');
      // Redirect to the replicant node.
      $redirectUrl = Url::fromUserInput($url);
      $form_state->setRedirectUrl($redirectUrl);
    }
  }

}

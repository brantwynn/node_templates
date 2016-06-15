<?php

namespace Drupal\node_templates\Form;

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
 * Class NodeTemplatesBlockForm.
 *
 * @package Drupal\node_templates\Form
 */
class NodeTemplatesBlockForm extends FormBase {

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
    return 'node_templates_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $form = [];
    if ($node != NULL && $nid = $node->id()) {
      $form['node_id'] = [
        '#type' => 'hidden',
        '#value' => $nid
      ];
      if (!$node->isDefaultTranslation()) {
        $form['langcode'] = [
          '#type' => 'hidden',
          '#value' => $node->language()->getId(),
        ];
      }
      $form['title'] = [
        '#type' => 'textfield',
        '#default_value' => $node->getTitle(),
        '#title' => 'Title',
      ];
      $node_type = NodeType::load($node->getType());
      if ($node_type->getThirdPartySetting('workbench_moderation', 'enabled')) {
        $form['template_draft'] = [
          '#type' => 'hidden',
          '#value' => 1
        ];
      }
      $comments = $node->comment->status;
      $form['comment_toggle'] = [
        '#type' => 'checkbox',
        '#default_value' => ($comments == 2 ? 1 : 0),
        '#title' => 'Enable Comments',
      ];
      $form['save'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create Template'),
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
    if ($langcode = $form_state->getValue('langcode')) {
      $node_title = $node->getTranslation($langcode)->title->value;
    }
    unset($node);
    // Get form values for the new template.
    $title = $form_state->getValue('title');
    $comments = ($form_state->getValue('comment_toggle') ? 2 : 1);
    if ($form_state->getValue('template_draft')) {
      $moderation = 'template';
    }
    // Create the template node using replicate.
    $replicator = new Replicator($this->entityTypeManager, $this->eventDispatcher);
    if ($template = $replicator->cloneByEntityId('node', $nid)) {
      // Set new values to template.
      if (isset($langcode)) {
        // Translation use case.
        $template->getTranslation($langcode)->title->value = $title;
        $template->getTranslation($langcode)->set('comment', $comments);
        if (isset($moderation)) {
          $template->getTranslation($langcode)->moderation_state->target_id = $moderation;
        }
      }
      else {
        // Default language use case.
        $template->setTitle($title);
        $template->set('comment', $comments);
        if (isset($moderation)) {
          $template->moderation_state->target_id = $moderation;
        }
      }
      // Save the new node template values.
      $template->save();
      // Send a friendly message.
      $message = $this->t('"@nodeTitle" [@nodeId] has been copied as "@templateTitle" [@templateId].', [
        '@nodeTitle' => $node_title,
        '@nodeId' => $nid,
        '@templateTitle' => $template->getTitle(),
        '@templateId' => $template->id(),
      ]);
      drupal_set_message($message, 'status');
      // Concatenate url for the template node redirect.
      $url = '/node/' . $template->id();
      // Redirect to the template node.
      $redirectUrl = Url::fromUserInput($url);
      $form_state->setRedirectUrl($redirectUrl);
    }
  }

}

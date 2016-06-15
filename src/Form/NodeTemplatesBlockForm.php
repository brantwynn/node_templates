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
      $form['langcode'] = [
        '#type' => 'hidden',
        '#value' => $node->language()->getId(),
      ];
      $form['title'] = [
        '#type' => 'textfield',
        '#default_value' => $node->getTitle(),
        '#title' => $this->t('Title'),
      ];
      $node_type = NodeType::load($node->getType());
      $form['moderation'] = [
        '#type' => 'hidden',
        '#value' => $node_type->getThirdPartySetting('workbench_moderation', 'enabled')
      ];
      $comment_status = $node->comment->status;
      $form['comment_toggle'] = [
        '#type' => 'checkbox',
        '#default_value' => ($comment_status == 2),
        '#title' => $this->t('Enable Comments'),
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
    // Return values to be used for the new template.
    $node_id = $form_state->getValue('node_id');
    $langcode = $form_state->getValue('langcode');
    $title = $form_state->getValue('title');
    $moderation = $form_state->getValue('moderation');
    $comments = ($form_state->getValue('comment_toggle') ? 2 : 1);
    // Load the target node to extract its title.
    $node = Node::load($node_id);
    $node_title = $node->getTranslation($langcode)->title->value;
    // Create the template using replicate.
    $replicator = new Replicator($this->entityTypeManager, $this->eventDispatcher);
    if ($template = $replicator->cloneByEntityId('node', $node_id)) {
      // Set the title for our new template.
      $template->getTranslation($langcode)->title->value = $title;
      // Set additional values for the template and its translations.
      $languages = $template->getTranslationLanguages();
      foreach ($languages as $id => $language) {
        // Comment toggle should persist across translations.
        $template->getTranslation($id)->set('comment', $comments);
        $test = $moderation;
        // Moderation state for all translations is 'template'.
        if ($moderation) {
          $template->getTranslation($id)->moderation_state->target_id = 'template';
        }
        // Ensure all translations are initially unpublished.
        $template->getTranslation($id)->status->value = 0;
      }
      // Save the new template.
      $template->save();
      // Send a friendly message.
      $message = $this->t("'@nodeTitle' [@nodeId] has been copied as '@templateTitle' [@templateId].", [
        '@nodeTitle' => $node_title,
        '@nodeId' => $node_id,
        '@templateTitle' => $template->getTranslation($langcode)->getTitle(),
        '@templateId' => $template->id(),
      ]);
      drupal_set_message($message, 'status');
      // Concatenate url for the template redirect.
      $url = '/node/' . $template->id();
      // Redirect to the template.
      $redirectUrl = Url::fromUserInput($url);
      $form_state->setRedirectUrl($redirectUrl);
    }
  }

}

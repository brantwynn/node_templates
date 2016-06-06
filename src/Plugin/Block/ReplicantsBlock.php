<?php

namespace Drupal\replicants\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'ReplicantsBlock' block.
 *
 * @Block(
 *  id = "replicants_block",
 *  admin_label = @Translation("Create Replicant"),
 *  context = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *  },
 *  category = @Translation("Forms")
 * )
 *
 */
class ReplicantsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilder $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $block = [];
    $node = $this->getContextValue('node');
    $block = [$this->formBuilder->getForm('\Drupal\replicants\Form\ReplicantsBlockForm', $node)];
    return $block;
  }

}

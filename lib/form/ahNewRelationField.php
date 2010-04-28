<?php

/**
 *
 * @author     Krzysztof Kotowicz <kkotowicz at gmail dot com>
 */
class ahNewRelationField extends sfWidgetForm
{

  protected function configure($options = array(), $attributes = array())
  {
    $this->addRequiredOption('containerName');
  }

  public function render($name, $value = null, $attributes = array(), $errors = array())
  {
    return $this->renderContentTag('button', $value ? $value : '+', array('type' => 'button', 'class' => 'ahAddRelation', 'rel' => $this->getOption('containerName')));
  }

  public function getJavaScripts()
  {
   return array('/ahDoctrineEasyEmbeddedRelationsPlugin/js/jquery.ah.js');
  }

}
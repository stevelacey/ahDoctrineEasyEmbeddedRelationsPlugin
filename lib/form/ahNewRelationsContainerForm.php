<?php

/**
 * Class used to embed new object forms in parent form
 *
 * @package    ahDoctrineEasyEmbeddedRelationsPlugin
 * @subpackage form
 * @author     Krzysztof Kotowicz <kkotowicz at gmail dot com>
 */
class ahNewRelationsContainerForm extends sfForm
{

  public function configure()
  {
    $this->setWidget('new_relation', new ahNewRelationField(array('containerName' => $this->getOption('containerName'))));
  }


  public function embedForm($name, sfForm $form, $decorator = null)
  {
    parent::embedForm($name, $form, $decorator);
    $this->widgetSchema->moveField('new_relation', sfWidgetFormSchema::LAST);
  }

}
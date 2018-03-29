<?php
namespace Incontact\Models;

/**
 * The keywords model
 */
class Keywords extends \Phalcon\Mvc\Model
{

    /**
     * @var integer
     */
    public $id;

    /**
     * @var integer
     */
    public $setting_id;

    /**
     * @var string
     */
    public $label;

    /**
     * Independent Column Mapping.
     */
    public function columnMap()
    {
        return array(
            'id' => 'id',
            'setting_id' => 'setting_id',
            'label' => 'label'
        );
    }

    /**
     * Initialization
     */
    public function initialize()
    {
        $this->skipAttributesOnCreate(array('id'));
    }
}

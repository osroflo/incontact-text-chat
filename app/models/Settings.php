<?php
namespace Incontact\Models;

/**
 * The settings model
 */
class Settings extends \Phalcon\Mvc\Model
{

    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $point_of_contact;

    /**
     * @var string
     */
    public $businnes_unit;

    /**
     * @var string
     */
    public $application_id;

    /**
     * @var string
     */
    public $application;

    /**
     * @var string
     */
    public $vendor;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $updated_at;

    /**
     * Independent Column Mapping.
     */
    public function columnMap()
    {
        return array(
            'id' => 'id',
            'point_of_contact' => 'point_of_contact',
            'businnes_unit' => 'businnes_unit',
            'application_id' => 'application_id',
            'application' => 'application',
            'vendor' => 'vendor',
            'description' => 'description',
            'updated_at' => 'updated_at'
        );
    }

    /**
     * Initialization
     */
    public function initialize()
    {
        $this->skipAttributesOnCreate(array('id'));
        $this->skipAttributesOnCreate(array('updated_at'));
    }
}

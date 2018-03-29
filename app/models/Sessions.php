<?php
namespace Incontact\Models;

/**
 * The session model
 */
class Sessions extends \Phalcon\Mvc\Model
{

    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $phone_number;

    /**
     * @var string
     */
    public $current_chat_session_id;

    /**
     * @var string
     */
    public $point_of_contact;

    /**
     * @var string
     */
    public $access_token;

    /**
     * @var string
     */
    public $date;

    /**
     * @var string
     */
    public $resource_server_base_uri;

    /**
     * Independent Column Mapping.
     */
    public function columnMap()
    {
        return array(
            'id' => 'id',
            'phone_number' => 'phone_number',
            'current_chat_session_id' => 'current_chat_session_id',
            'point_of_contact' => 'point_of_contact',
            'access_token' => 'access_token',
            'date' => 'date',
            'resource_server_base_uri' => 'resource_server_base_uri'
        );
    }

    /**
     * Initialization
     */
    public function initialize()
    {
        $this->skipAttributesOnCreate(array('date'));
    }
}

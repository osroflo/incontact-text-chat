<?php
namespace Incontact;

use Incontact\Models\Settings;
use Incontact\Models\Keywords;

/**
 * Manage incontact application settings
 */
class Applications
{
    /**
     * Get all available application settings
     * @return json
     */
    public function getAll()
    {
        // get settings
        $settings = Settings::find();

        if ($settings) {
            foreach ($settings as $setting) {
                $a['id'] = $setting->id;
                $a['point_of_contact'] = $setting->point_of_contact;
                $a['businnes_unit'] = $setting->businnes_unit;
                $a['application_id'] = $setting->application_id;
                $a['application'] = $setting->application;
                $a['vendor'] = $setting->vendor;
                $a['description'] = $setting->description;
                $a['updated_at'] = $setting->updated_at;

                $keywords = Keywords::find("setting_id = {$setting->id}");

                if ($keywords) {
                    $k=array();

                    foreach ($keywords as $keyword) {
                        $keys = array(
                            'id'  =>  $keyword->id,
                            'label'  =>  $keyword->label,

                        );

                        $k[] = $keys;
                    }
                }

                $a['keywords'] = $k;

                $app[] = $a;
            }

            $applications = $app;
        } else {
            $applications = array('no applications found');
        }

        return json_encode(array(
            "status" => "success",
            "count" => count($settings),
            "applications" => $applications
        ));
    }

    /**
     * Create application settings
     *
     * @param  array  $params Settings
     * @return json
     */
    public function create($params = array())
    {
        try {
            $settings = new Settings();

            if (count($params) > 5) {
                $settings->point_of_contact = $params['point_of_contact'];
                $settings->businnes_unit = $params['businnes_unit'];
                $settings->application_id = $params['application_id'];
                $settings->application = $params['application'];
                $settings->vendor = $params['vendor'];
                $settings->description = $params['description'];
                $allowed = $settings->save();

                if ($allowed) {
                    $keyword_list = explode(',', $params['keywords']);

                    if (count($keyword_list) > 0) {
                        foreach ($keyword_list as $key) {
                            $keyword = new Keywords();
                            $keyword->label = $key;
                            $keyword->setting_id = $settings->id;
                            $keyword->save();
                        }
                    }

                    $status="success";
                    $reason="Application was created";
                } else {
                    $status="fail";
                    $reason="Application can not be stored";
                }
            } else {
                $status = "fail";
                $reason = $params;
            }
        } catch (\Exception $e) {
            $status = "fail";
            $reason = $e->getMessage();
        }

        return json_encode(array(
            "status" => $status,
            "reason" => array(
                "message" => "Incomplete or empty fields, at least seven fields should be passed",
                "fields" => $params
            )
        ));
    }

    /**
     * Update application settings
     *
     * @param  integer $id
     * @param  array  $params
     * @return json
     */
    public function update($id, $params = array())
    {
        try {
            if (count($params) > 5) {
                $settings = Settings::findFirst($id);

                if ($settings) {
                    $settings->point_of_contact = $params['point_of_contact'];
                    $settings->businnes_unit = $params['businnes_unit'];
                    $settings->application_id = $params['application_id'];
                    $settings->application = $params['application'];
                    $settings->vendor = $params['vendor'];
                    $settings->description = $params['description'];
                    $allowed = $settings->save();

                    if ($allowed) {
                        $status="success";
                        $reason="Application was created";
                    } else {
                        $status="fail";
                        $reason="Application can not be saved";
                    }
                } else {
                    $status="fail";
                    $reason="Application was not found";
                }
            } else {
                $status = "failed";
                $reason = $params;
            }
        } catch (\Exception $e) {
            $status = "fail";
            $reason = $e->getMessage();
        }

        return json_encode(array(
            "status" => $status,
            "reason" => array(
                "message" => "Incomplete or empty fields, at least seven fields should be passed",
                "fields" => $params
            )
        ));
    }

    /**
     * Delete setting
     *
     * @param  integer $id The unique identifier
     * @return json
     */
    public function delete($id)
    {
        if ($id) {
            try {
                $status = "fail";
                $setting = Settings::findFirst($id);

                if ($setting) {
                    if ($setting->delete()) {
                        $keywords_was_not_deleted = false;

                        foreach ($keywords = Keywords::find("setting_id = $id") as $keyword) {
                            if ($keyword->delete() == false) {
                                $keywords_was_not_deleted = true;
                            }
                        }

                        $status = "success";
                        $reason =  ($keywords_was_not_deleted) ? "Application was deleted but one or more keywords were not deleted" : "Application was deleted";
                    } else {
                        $reason = $setting->getMessages();
                    }
                } else {
                    $reason = "Application was not found";
                }
            } catch (\Exception $e) {
                $status = "fail";
                $reason = $e->getMessage();
            }
        } else {
            $status = "fail";
            $reason = "No ID was passed";
        }


        return json_encode(array("status" => $status, "reason" => $reason));
    }

    /**
     * Create a keyword to trigger chat
     *
     * @param  integer $setting_id The setting unique identifier
     * @param  string $label       The keyword name
     * @return json
     */
    public function createKeyword($setting_id, $label)
    {
        if ($setting_id != '' && $label != '') {
            try {
                $keyword = new Keywords;
                $keyword->label = $label;
                $keyword->setting_id = $setting_id;

                $keyword->save();

                $status = "success";
                $id = $keyword->id;
                $reason="";
            } catch (\Exception $e) {
                $status = "fail";
                $reason = $e->getMessage();
                $id="";
            }
        } else {
            $status = "fail";
            $reason = "";
            $id="";
        }

        return json_encode(array("status" => $status, "reason" => $reason, "id" => $keyword->id));
    }

    /**
     * Remove  keyword
     *
     * @param  integer $id The keyword unique identifier
     * @return json
     */
    public function removeKeyword($id)
    {
        if ($id) {
            try {
                $status = "fail";
                $keyword = Keywords::findFirst($id);

                if ($keyword) {
                    if ($keyword->delete()) {
                        $status = "success";
                        $reason = "Keyword was deleted";
                    } else {
                        $reason = $keyword->getMessages();
                    }
                } else {
                    $reason = "Keyword was not found";
                }
            } catch (\Exception $e) {
                $status = "fail";
                $reason = $e->getMessage();
            }
        } else {
            $status = "fail";
            $reason = "No ID was passed";
        }

        return json_encode(array("status" => $status, "reason" => $reason));
    }

    /**
     * Update keyword
     *
     * @param  integer $id    Keyword unique identifier
     * @param  string $label  Keyword name
     * @return json
     */
    public function updateKeyword($id, $label)
    {
        if ($id != '' && $label != '') {
            try {
                $status = "fail";
                $keyword = Keywords::findFirst($id);

                if ($keyword) {
                    $keyword->label = $label;
                    $keyword->save();

                    $status = "success";
                    $id = $keyword->id;
                    $reason="";
                } else {
                    $reason = "Keyword was not found";
                }
            } catch (\Exception $e) {
                $status = "fail";
                $reason = $e->getMessage();
                $id="";
            }
        } else {
            $status = "fail";
            $reason = "";
            $id="";
        }

        return json_encode(array(
            "status" => $status,
            "reason" => $reason,
            "id" => $keyword->id
        ));
    }
}

<?php
use Incontact\Models\Sessions;
use App\Lib\ThreeScale\ThreeScaleClient;

try {
    // read the configuration
    $config = include __DIR__ . "/app/config/config.php";

    // read auto-loader
    include __DIR__ . "/app/config/loader.php";

    // create a DI (dependency injection container)
    $di = new Phalcon\DI\FactoryDefault();

    // setup the database service
    $di->set('db', function () use ($config) {
        return new \Phalcon\Db\Adapter\Pdo\Mysql(array(
            "host" => $config->database->host,
            "username" => $config->database->username,
            "password" => $config->database->password,
            "dbname" => $config->database->dbname
        ));
    });

    $app = new Phalcon\Mvc\Micro($di);

    // executed before every route executed
    $app->before(function () use ($app, $config) {

        // keep your provider key secret
        $client =  new ThreeScaleClient($config->ThreeScale->provider_key);

        // you will usually obtain app_id and app_key from the request params
        $response = $client->authrep_with_user_key($config->ThreeScale->useer_key, array('hits' => 1));

        if ($response->isSuccess()) {
            return true;
        } else {
            echo "Error: " . $response->getErrorMessage();
            return false;
        }
    });

    /**
     * Routes
     */
    $app->get('/', function () {
        echo "<h1>Welcome to InContact text to chat API!</h1> <br>";
    });

    /**
     * Text to chat accepts certain keywords like: help, food, shelter, dissaster, etc.
     * Keyword is the trigger to start a chat session.
     *
     * @param string keyword
     * @return json {
     *    "status": "success",
     *    "reason": {
     *       "setting_id": "1"    <-- setting_id will be used to get the application settings
     *    }
     * }
     *
     */
    $app->get('/v1/keywords', function () {
        $request = new Phalcon\Http\Request();
        $smschat = new Incontact\SMSChat();

        echo $smschat->isValidKeyWord($request->get('keyword'));
    });


    /**
     * Check if there is an InContact Chat session for the phone number,
     * if session exists then allow to send text
     *
     * @param string phone
     * @param string txt
     * @return json response
     */
    $app->get('/v1/sessions', function () {
        $request = new Phalcon\Http\Request();
        $status = '';
        $reason = '';
        $phone = strip_tags($request->get('phone'));
        $txt = strip_tags($request->get('txt'));

        if ($phone != '' && $txt != '') {
            $smschat = new Incontact\SMSChat();

            if ($smschat->isValidPhone($phone)) {
                $smschat->phone_number = $phone;

                if ($smschat->isLiveSession()) {
                    $smschat->sendTextToChat($text);
                    $status = "success";
                    $reason = "session is live";
                } else {
                    // check keyword to know if it is intended to be sent to SMSCHAT
                    $kwd = json_decode($smschat->isValidKeyWord($txt));

                    if ($kwd->status == 'success') {
                        $setting_id = $kwd->reason->setting_id;
                        $rqt = json_decode($smschat->request($phone, $txt, $setting_id));
                        $status = $rqt->status;
                        $reason =  $rqt->reason;
                    } else {
                        $status = 'failure';
                        $reason =  "No keyword found";
                    }
                }
            } else {
                $status = 'failure';
                $reason =  'phone number is invalid';
            }
        } else {
            $status = 'failure';
            $reason =  array('phone' => $phone, 'txt'=>$txt);
        }

        echo json_encode(array("status"=>$status, "reason"=>$reason));
    });


    /**
     * Get requests from tropo.com to start a chat session in the InContact GUI.
     *
     * @param string $phone
     * @param string $txt
     * @param integer $setting_id  This id was previously get by tropo
     * @return json
     */
    $app->get('/v1/request', function () {
        $request = new Phalcon\Http\Request();
        $phone = strip_tags($request->get('phone'));
        $txt = strip_tags($request->get('txt'));
        $setting_id = strip_tags($request->get('settingid'));

        $smschat = new Incontact\SMSChat();

        echo $smschat->request($phone, $txt, $setting_id);
    });

    // Routes for consumed by the GUI

    /**
     * Create aplication setting.
     *
     * @return json
     */
    $app->post('/v1/applications', function () {
        $request = new Phalcon\Http\Request();
        $params = array_map('strip_tags', $request->getPost());

        $applications = new Incontact\Applications();
        echo $applications->create($params);
    });

    /**
     * Read aplications settings.
     *
     * @return json
     */
    $app->get('/v1/applications', function () {
        $applications = new Incontact\Applications();
        echo $applications->getAll();
    });

    /**
     * Update aplication setting.
     *
     * @return json
     */
    $app->put('/v1/applications/{id:[0-9]+}', function ($id) {
        $request = new Phalcon\Http\Request();
        $params = array_map('strip_tags', $request->getPut());

        $applications = new Incontact\Applications();
        echo $applications->update($id, $params);
    });

    /**
     * Delete application setting.
     *
     * @return json
     */
    $app->delete('/v1/applications/{id:[0-9]+}', function ($id) {
        $applications = new Incontact\Applications();
        echo $applications->delete($id);
    });






    /**
     * Create keyword.
     *
     * @return json
     */
    $app->post('/v1/keywords/{id:[0-9]+}', function ($id) {
        $request = new Phalcon\Http\Request();
        $label = strip_tags($request->getPost('label'));

        $applications = new Incontact\Applications();

        echo $applications->createKeyword($id, $label);
    });

    /**
     * Update keyword
     */
    $app->put('/v1/keywords/{id:[0-9]+}', function ($id) {
        $request = new Phalcon\Http\Request();
        $label = strip_tags($request->getPut('label'));

        $applications = new Incontact\Applications();
        echo $applications->updateKeyword($id, $label);
    });

    /**
     * Delete keyword
     */
    $app->delete('/v1/keywords/{id:[0-9]+}', function ($id) {
        $applications = new Incontact\Applications();
        echo $applications->removeKeyword($id);
    });

    /**
     * Not found endpoints
     */
    $app->notFound(function () use ($app) {
        $app->response->setStatusCode(404, "Not Found")->sendHeaders();

        $request = new Phalcon\Http\Request();
        $method = array_map('strip_tags', $request->getMethod());

        echo json_encode(array("status"=>"fail", "reason"=>"The requested resource was not found or the method $method is not supported"));
    });

    $app->handle();
} catch (\Exception $e) {
    echo $e->getMessage();
}

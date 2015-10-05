<?php
require_once dirname(__FILE__) . '/../bootstrap.php';

use LearnEat\Middleware\TokenOverBasicAuth;
use LearnEat\Exception;
use LearnEat\Exception\ValidationException;
use LearnEat\Routes;

// General API group
$app->group(
    '/api',
    function () use ($app, $log) {

        // Common to all sub routes

        // Get contacts
        $app->get('/', function () {
            echo "<h1>This can be the documentation entry point</h1>";
            echo "<p>This URL could also contain discovery"
            ." information in side the headers</p>";
        });

        // Group for API Version 1
        $app->group(
            '/v1',
            // API Methods
            function () use ($app, $log) {

                // Get contact with ID
                $app->get(
                    '/contacts/:id',
                    function ($id) use ($app, $log) {

                        $id = filter_var(
                            filter_var($id, FILTER_SANITIZE_NUMBER_INT),
                            FILTER_VALIDATE_INT
                        );

                        if (false === $id) {
                            throw new ValidationException("Invalid contact ID");
                        }

                        $contact = \ORM::forTable('learneat_contacts')->findOne($id);
                        if ($contact) {

                            $output = $contact->asArray();

                            if ('notes' === $app->request->get('embed')) {
                                $notes = \ORM::forTable('notes')
                                    ->where('contact_id', $id)
                                    ->orderByDesc('id')
                                    ->findArray();

                                if (!empty($notes)) {
                                    $output['notes'] = $notes;
                                }
                            }

                            echo json_encode($output, JSON_PRETTY_PRINT);
                            return;
                        }
                        $app->notFound();
                    }
                );

                $app->get('/classes', '\LearnEat\Routes\CookingClass:getClasses');
                $app->get('/class/:id', '\LearnEat\Routes\CookingClass:getClass');
            }
        );
    }
);

// Public human readable home page
$app->get(
    '/',
    function () use ($app, $log) {
        echo "<h1>Hello, this can be the public App Interface</h1>";
    }
);

// JSON friendly errors
// NOTE: debug must be false
// or default error template will be printed
$app->error(function (\Exception $e) use ($app, $log) {

    $mediaType = $app->request->getMediaType();

    $isAPI = (bool) preg_match('|^/api/v.*$|', $app->request->getPath());

    // Standard exception data
    $error = array(
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    );

    // Graceful error data for production mode
    if (!in_array(
        get_class($e),
        array('API\\Exception', 'API\\Exception\ValidationException')
    )
        && 'production' === $app->config('mode')) {
        $error['message'] = 'There was an internal error';
        unset($error['file'], $error['line']);
    }

    // Custom error data (e.g. Validations)
    if (method_exists($e, 'getData')) {
        $errors = $e->getData();
    }

    if (!empty($errors)) {
        $error['errors'] = $errors;
    }

    $log->error($e->getMessage());
    if ('application/json' === $mediaType || true === $isAPI) {
        $app->response->headers->set(
            'Content-Type',
            'application/json'
        );
        echo json_encode($error, JSON_PRETTY_PRINT);
    } else {
        echo '<html>
        <head><title>Error</title></head>
        <body><h1>Error: ' . $error['code'] . '</h1><p>'
        . $error['message']
        .'</p></body></html>';
    }

});

/// Custom 404 error
$app->notFound(function () use ($app) {

    $mediaType = $app->request->getMediaType();

    $isAPI = (bool) preg_match('|^/api/v.*$|', $app->request->getPath());


    if ('application/json' === $mediaType || true === $isAPI) {

        $app->response->headers->set(
            'Content-Type',
            'application/json'
        );

        echo json_encode(
            array(
                'code' => 404,
                'message' => 'Not found'
            ),
            JSON_PRETTY_PRINT
        );

    } else {
        echo '<html>
        <head><title>404 Page Not Found</title></head>
        <body><h1>404 Page Not Found</h1><p>The page you are
        looking for could not be found.</p></body></html>';
    }
});

$app->run();

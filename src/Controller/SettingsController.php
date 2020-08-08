<?php

namespace App\Controller;

use App\TwitchConfig;
use App\TwitchHelper;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Views\Twig;

/**
 * Settings page
 */
class SettingsController
{

    /**
     * @var Twig
     */
    private $twig;

    public function __construct(Twig $twig) {
        $this->twig = $twig;
    }

    public function settings(Request $request, Response $response, array $args) {
        
        

        return $this->twig->render($response, 'settings.twig', [
            'streamers' => TwitchConfig::getStreamers(),
        ]);

    }

    public function settings_save(Request $request, Response $response, array $args) {
        
        $app_name               = $_POST['app_name'];
        $vods_to_keep           = $_POST['vods_to_keep'];
        $storage_per_streamer   = $_POST['storage_per_streamer'];
        $api_client_id          = $_POST['api_client_id'];
        $api_secret             = $_POST['api_secret'];

        TwitchConfig::$config['app_name'] = $app_name;
        TwitchConfig::$config['vods_to_keep'] = (int)$vods_to_keep;
        TwitchConfig::$config['storage_per_streamer'] = (int)$storage_per_streamer;
        TwitchConfig::$config['api_client_id'] = $api_client_id;
        if($api_secret) TwitchConfig::$config['api_secret'] = $api_secret;

        TwitchConfig::saveConfig();

        return $response->withHeader('Location', '/settings')->withStatus(200);

    }

}
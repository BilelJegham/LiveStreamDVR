<?php

namespace App;

use GuzzleHttp\Client;

class TwitchHelper {

	public static $accessToken;

	public static $accessTokenFile = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "oauth.bin";

	public static $accessTokenExpire = 60 * 60 * 24 * 60; // 60 days
	public static $accessTokenRefresh = 60 * 60 * 24 * 30; // 30 days

	public static $game_db = null;

	public static $guzzler;

	const LOG_ERROR = "ERROR";
	const LOG_WARNING = "WARNING";
	const LOG_INFO = "INFO";
	const LOG_DEBUG = "DEBUG";

	/**
	 * Set up directories for first use
	 *
	 * @return void
	 */
	public static function setupDirectories(){
		mkdir(__DIR__ . "/../logs");
		mkdir(__DIR__ . "/../payloads");
		mkdir(__DIR__ . "/../public/vods");
		mkdir(__DIR__ . "/../public/vods");
		mkdir(__DIR__ . "/../public/vods");
	}

	/**
	 * Get OAuth token from Twitch. If it exists on disk, read from that instead. If it's too old then get a new one.
	 *
	 * @param boolean $force Force fetch a new token
	 * @return string Token
	 */
	public static function getAccessToken( $force = false ){

		// token should last 60 days, delete it after 30 just to be sure
		if( file_exists( self::$accessTokenFile ) ){
			$tokenRefresh = time() - filemtime( self::$accessTokenFile ) > TwitchHelper::$accessTokenRefresh;
			$tokenExpire = time() - filemtime( self::$accessTokenFile ) > TwitchHelper::$accessTokenExpire;
			if( $tokenRefresh || $tokenExpire ){ // TODO: fix this, i'm bad at math
				self::log( self::LOG_INFO, "Deleting old access token");
				unlink( self::$accessTokenFile );
			}
		}
		

		if( !$force && file_exists( self::$accessTokenFile ) ){
			self::log( self::LOG_DEBUG, "Fetched access token from cache");
			return file_get_contents( self::$accessTokenFile );
		}

		// oauth2
		$oauth_url = 'https://id.twitch.tv/oauth2/token';
		$client = new \GuzzleHttp\Client();
		$response = $client->post($oauth_url, [
			'query' => [
				'client_id' => TwitchConfig::cfg('api_client_id'),
				'client_secret' => TwitchConfig::cfg('api_secret'),
				'grant_type' => 'client_credentials'
			],
			'headers' => [
				'Client-ID: ' . TwitchConfig::cfg('api_client_id')
			]
		]);

		$server_output = $response->getBody()->getContents();

		$json = json_decode( $server_output, true );


		if( !$json || !isset($json['access_token']) || !$json['access_token'] ){
			self::log( TwitchHelper::LOG_ERROR, "Failed to fetch access token: " . $server_output);
			throw new \Exception( "Failed to fetch access token: " . $server_output );
			return false;
		}

		$access_token = $json['access_token'];

		self::$accessToken = $access_token;

		file_put_contents( self::$accessTokenFile, $access_token );

		self::log( TwitchHelper::LOG_INFO, "Fetched new access token");

		return $access_token;

	}
	
	/**
	 * Log a string to the current log file
	 *
	 * @param const $level
	 * @param string $text
	 * @return void
	 */
	public static function log( $level, $text ){

		if( !TwitchConfig::cfg("debug") && $level == self::LOG_DEBUG ) return;
		
		$filename 		= __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "logs" . DIRECTORY_SEPARATOR . date("Y-m-d") . ".log";
		$filename_json 	= __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "logs" . DIRECTORY_SEPARATOR . date("Y-m-d") . ".log.json";
		
		$log_text = file_exists( $filename ) ? file_get_contents( $filename ) : '';
		$log_json = file_exists( $filename_json ) ? json_decode( file_get_contents( $filename_json ), true ) : [];

		$date = new \DateTime();

		$text_line = $date->format("Y-m-d H:i:s.v") . " | <" . $level . "> " . $text;

		$log_text .= "\n" . $text_line;

		$log_data = [
			"date" => microtime(true),
			"level" => $level,
			"text" => $text
		];

		$log_json[] = $log_data;

		file_put_contents($filename, $log_text);

		file_put_contents($filename_json, json_encode($log_json));
		
	}

	/**
	 * Get Twitch channel ID from username
	 *
	 * @param string $username
	 * @return string
	 */
	public static function getChannelId( $username ){

		$streamers_file =  __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "streamers.json";

		$json_streamers = json_decode( file_get_contents( $streamers_file ), true );

		if( $json_streamers && $json_streamers[$username] ){
			self::log( self::LOG_DEBUG, "Fetched channel id from cache for " . $username);	
			return $json_streamers[$username];
		}

		$access_token = self::getAccessToken();

		if( !$access_token ){
			throw new \Exception('Fatal error, could not get access token for channel id request');
			return false;
		}

		// webhook list
		/*
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://api.twitch.tv/helix/users?login=' . $username);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
		    'Authorization: Bearer ' . $access_token,
		    'Client-ID: ' . TwitchConfig::cfg('api_client_id')
		]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

		$server_output = curl_exec($ch);

		curl_close ($ch);

		$json = json_decode( $server_output, true );
		*/
		
		/*
		$client = new \GuzzleHttp\Client([
			'base_uri' => 'https://api.twitch.tv',
			'headers' => [
				'Client-ID' => TwitchConfig::cfg('api_client_id'),
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . TwitchHelper::getAccessToken(),
			]
		]);
		*/

		$response = self::$guzzler->request('GET', '/helix/users', [
			'query' => ['login' => $username]
		]);

		$server_output = $response->getBody()->getContents();
		$json = json_decode( $server_output, true );

		if( !$json["data"] ){
			self::log(self::LOG_ERROR, "Failed to fetch channel id: " . $server_output);
			// throw new Exception( "Failed to fetch channel id: " . $server_output );
			return false;
		}

		$id = $json["data"][0]["id"];
		
		$json_streamers[ $username ] = $id;
		file_put_contents( $streamers_file, json_encode($json_streamers) );

		self::log( self::LOG_INFO, "Fetched channel id online for " . $username);

		return $id;

	}

	/**
	 * Return videos for a streamer id
	 *
	 * @param int $streamer_id
	 * @return array|false
	 */
	public static function getVideos( $streamer_id ){

		/*
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://api.twitch.tv/helix/videos?user_id=' . $streamer_id);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . self::getAccessToken(),
		    'Client-ID: ' . TwitchConfig::cfg('api_client_id')
		]);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

		$server_output = curl_exec($ch);

		curl_close($ch);

		$json = json_decode( $server_output, true );
		*/
		
		/*
		$client = new \GuzzleHttp\Client([
			'base_uri' => 'https://api.twitch.tv',
			'headers' => [
				'Client-ID' => TwitchConfig::cfg('api_client_id'),
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . TwitchHelper::getAccessToken(),
			]
		]);
		*/

		$response = self::$guzzler->request('GET', '/helix/videos', [
			'query' => ['user_id' => $streamer_id]
		]);

		$server_output = $response->getBody()->getContents();
		$json = json_decode( $server_output, true );

		if( !$json['data'] ){
			self::log( self::LOG_ERROR, "No videos found for user id " . $streamer_id);
			return false;
		}

		self::log( self::LOG_INFO, "Querying videos for streamer id " . $streamer_id);

		return $json['data'] ?: false;

	}

	/**
	 * Get Twitch video by video ID
	 *
	 * @param string $video_id
	 * @return array
	 */
	public static function getVideo( $video_id ){

		/*
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://api.twitch.tv/helix/videos?id=' . $video_id);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . self::getAccessToken(),
		    'Client-ID: ' . TwitchConfig::cfg('api_client_id')
		]);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

		$server_output = curl_exec($ch);

		curl_close($ch);

		$json = json_decode( $server_output, true );
		*/

		/*
		$client = new \GuzzleHttp\Client([
			'base_uri' => 'https://api.twitch.tv',
			'headers' => [
				'Client-ID' => TwitchConfig::cfg('api_client_id'),
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . TwitchHelper::getAccessToken(),
			]
		]);
		*/

		$response = self::$guzzler->request('GET', '/helix/videos', [
			'query' => ['id' => $video_id]
		]);

		$server_output = $response->getBody()->getContents();
		$json = json_decode( $server_output, true );

		if( !$json['data'] ){
			self::log( self::LOG_ERROR, "No video found for video id " . $video_id);
			return null;
		}

		self::log( self::LOG_INFO, "Querying video info for id " . $video_id);

		return $json['data'][0];

	}

	/**
	 * Return videos for a streamer id
	 *
	 * @param int $streamer_id
	 * @return array|false
	 */
	public static function getStreams( $streamer_id ){

		/*
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://api.twitch.tv/helix/streams?user_id=' . $streamer_id);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . self::getAccessToken(),
		    'Client-ID: ' . TwitchConfig::cfg('api_client_id')
		]);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

		$server_output = curl_exec($ch);

		curl_close($ch);

		$json = json_decode( $server_output, true );
		*/
		/*
		$client = new \GuzzleHttp\Client([
			'base_uri' => 'https://api.twitch.tv',
			'headers' => [
				'Client-ID' => TwitchConfig::cfg('api_client_id'),
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . TwitchHelper::getAccessToken(),
			]
		]);
		*/

		$response = self::$guzzler->request('GET', '/helix/streams', [
			'query' => ['user_id' => $streamer_id]
		]);

		$server_output = $response->getBody()->getContents();
		$json = json_decode( $server_output, true );

		if( !$json['data'] ){
			self::log( self::LOG_ERROR, "No streams found for user id " . $streamer_id);
			return false;
		}

		self::log( self::LOG_INFO, "Querying streams for streamer id " . $streamer_id);

		return $json['data'] ?: false;

	}

	/**
	 * Get game by ID from the cache
	 *
	 * @param string $id
	 * @return array
	 */
	public static function getGame( $id ){

		if( self::$game_db ){
			return self::$game_db[$id];
		}

		self::$game_db = json_decode( file_get_contents( __DIR__ . '/../config/games_v2.json' ), true );

		return self::getGame($id);

	}

	/**
	 * Parse twitch format duration: 1h1m1s
	 * Returns seconds.
	 *
	 * @param string $text Twitch duration
	 * @return int Seconds
	 */
	public static function parseTwitchDuration( $text ){

		preg_match('/([0-9]+)h/', $text, $hours_match);
		preg_match('/([0-9]+)m/', $text, $minutes_match);
		preg_match('/([0-9]+)s/', $text, $seconds_match);

		$total_seconds = 0;

		if($seconds_match[1]) $total_seconds += $seconds_match[1];
		if($minutes_match[1]) $total_seconds += $minutes_match[1] * 60;
		if($hours_match[1]) $total_seconds += $hours_match[1] * 60 * 60;

		return $total_seconds;

	}

	/**
	 * https://www.php.net/manual/en/function.realpath.php#84012
	 *
	 * @param string $path
	 * @return string
	 */
	public static function get_absolute_path($path) {
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = array();
        foreach ($parts as $part) {
            if ('.' == $part) continue;
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        return implode(DIRECTORY_SEPARATOR, $absolutes);
	}
	
	public static function checkForDeletedVods(){

		$deleted = false;

		TwitchHelper::log( TwitchHelper::LOG_INFO, "Check for deleted vods");
		
		$streamers = TwitchConfig::getStreamers();

		foreach( $streamers as $streamer ){

			$vods = glob( TwitchHelper::vod_folder() . DIRECTORY_SEPARATOR . $streamer['username'] . "_*.json");

			foreach( $vods as $k => $v ){

				$vodclass = new TwitchVOD();
				$vodclass->load($v);

				if( !$vodclass->is_recording ){

					$isvalid = $vodclass->checkValidVod();

					if(!$isvalid){
						TwitchHelper::log( TwitchHelper::LOG_WARNING, "VOD deleted: " . $vodclass->basename );
						$deleted = true;
					}

				}

			}		

		}

		return $deleted;

	}

	public static function getNiceDuration($durationInSeconds) {

		$duration = '';
		$days = floor($durationInSeconds / 86400);
		$durationInSeconds -= $days * 86400;
		$hours = floor($durationInSeconds / 3600);
		$durationInSeconds -= $hours * 3600;
		$minutes = floor($durationInSeconds / 60);
		$seconds = $durationInSeconds - $minutes * 60;
	  
		if($days > 0) {
		  $duration .= $days . 'd';
		}
		if($hours > 0) {
		  $duration .= ' ' . $hours . 'h';
		}
		if($minutes > 0) {
		  $duration .= ' ' . $minutes . 'm';
		}
		if($seconds > 0) {
		  $duration .= ' ' . $seconds . 's';
		}
		return trim($duration);
	}

	/**
	 * https://stackoverflow.com/a/2510459
	 *
	 * @param integer $bytes
	 * @param integer $precision
	 * @return string
	 */
	public static function formatBytes($bytes, $precision = 2) { 
		$units = array('B', 'KB', 'MB', 'GB', 'TB'); 
	
		$bytes = max($bytes, 0); 
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
		$pow = min($pow, count($units) - 1); 
	
		// Uncomment one of the following alternatives
		$bytes /= pow(1024, $pow);
		// $bytes /= (1 << (10 * $pow)); 
	
		return round($bytes, $precision) . ' ' . $units[$pow]; 
	} 

	/**
	 * Return a human readable duration in seconds
	 * TODO: 24+ hour durations
	 *
	 * @param int $duration
	 * @return string
	 */
	public static function printHumanDuration( $duration ){

		$time = new \DateTime();
		$time->setTimestamp( $duration );

		return $time->format("H:i:s");

	}

	/**
	 * Subscribe to a streamer
	 *
	 * @param string $streamer_name
	 * @return string|bool
	 */
	public static function sub( $streamer_name ){
		return self::sub_handler($streamer_name, 'subscribe');
	}

	/**
	 * Unsubscribe to a streamer
	 *
	 * @param string $streamer_name
	 * @return string|bool
	 */
	public static function unsub( $streamer_name ){
		return self::sub_handler($streamer_name, 'unsubscribe');
	}

	private static function sub_handler( $streamer_name, $mode = 'subscribe' ){

		/**
		 * TODO: Fix this
		 */
		/*
		 if( !TwitchConfig::getStreamers()[$streamer_name] ) {
			$this->notify('Streamer not found: ' . $streamer_name, '[' . $streamer_name . '] [subscribing error]', self::NOTIFY_ERROR);
			throw new Exception('Streamer not found: ' . $streamer_name);
			return false;
		}
		*/

		TwitchHelper::log( TwitchHelper::LOG_INFO, "Calling " . $mode . " for " . $streamer_name);

		$streamer_id = TwitchHelper::getChannelId($streamer_name);

		if( !$streamer_id ) {
			TwitchHelper::log( TwitchHelper::LOG_ERROR, "Streamer ID not found for: " . $streamer_name );
			throw new \Exception('Streamer ID not found for: ' . $streamer_name);
			return false;
		}

		$url = 'https://api.twitch.tv/helix/webhooks/hub';
		$method = 'POST';

		$data = [
			'hub.callback' => TwitchConfig::cfg('hook_callback'),
			'hub.mode' => $mode,
			'hub.topic' => 'https://api.twitch.tv/helix/streams?user_id=' . $streamer_id,
			'hub.lease_seconds' => TwitchConfig::cfg('sub_lease')
		];

		$data_string = json_encode($data);

		/*
		$client = new \GuzzleHttp\Client([
			'base_uri' => 'https://api.twitch.tv',
			'headers' => [
				'Client-ID' => TwitchConfig::cfg('api_client_id'),
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . TwitchHelper::getAccessToken(),
			]
		]);
		*/

		$response = self::$guzzler->request('POST', '/helix/webhooks/hub', [
			'json' => $data
		]);

		$server_output = $response->getBody()->getContents();
		$http_code = $response->getStatusCode();		

		$json = json_decode( $server_output, true );
		

		if( $http_code == 202 ){

			TwitchHelper::log( TwitchHelper::LOG_INFO, "Successfully " . $mode . " to " . $streamer_name);

			// $this->notify($server_output, '[' . $streamer_name . '] [subscribing]', self::NOTIFY_GENERIC);

			return true;

		}else{

			TwitchHelper::log( TwitchHelper::LOG_ERROR, "Failed to " . $mode . " to " . $streamer_name . " | " . $server_output . " | HTTP " . $http_code );
			
			return $server_output;

		}

	}

	/**
	 * Returns the raw json data of your subscriptions
	 *
	 * @return string
	 */
	public static function getSubs(){

		TwitchHelper::log( TwitchHelper::LOG_INFO, "Requesting subscriptions list");

		/*
		$client = new \GuzzleHttp\Client([
			'base_uri' => 'https://api.twitch.tv',
			'headers' => [
				'Client-ID' => TwitchConfig::cfg('api_client_id'),
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . TwitchHelper::getAccessToken(),
			]
		]);
		*/

		$response = self::$guzzler->request('GET', '/helix/webhooks/subscriptions', [
			// 'headers' => $headers
		]);

		$server_output = $response->getBody()->getContents();	

		$json = json_decode( $server_output, true );

		return $json;

	}

	// path helpers
	public static function is_windows(){
		return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	}

	public static function path_ffmpeg(){
		if( TwitchConfig::cfg('ffmpeg_path') ) return TwitchConfig::cfg('ffmpeg_path');
		if( file_exists("/usr/bin/ffmpeg") ) return "/usr/bin/ffmpeg";
		return "";
	}

	public static function path_streamlink(){
		return TwitchConfig::cfg('bin_dir') . DIRECTORY_SEPARATOR . "streamlink" . ( self::is_windows() ? '.exe' : '' );
	}

	public static function path_youtubedl(){
		return TwitchConfig::cfg('bin_dir') . DIRECTORY_SEPARATOR . "youtube-dl" . ( self::is_windows() ? '.exe' : '' );
	}

	public static function path_tcd(){
		return TwitchConfig::cfg('bin_dir') . DIRECTORY_SEPARATOR . "tcd" . ( self::is_windows() ? '.exe' : '' );
	}

	public static function path_pipenv(){
		return TwitchConfig::cfg('bin_dir') . DIRECTORY_SEPARATOR . "pipenv" . ( self::is_windows() ? '.exe' : '' );
	}

	public static function vod_folder(){
		return __DIR__ . "/../public/vods";
	}

}

TwitchHelper::$guzzler = new \GuzzleHttp\Client([
	'base_uri' => 'https://api.twitch.tv',
	'headers' => [
		'Client-ID' => TwitchConfig::cfg('api_client_id'),
		'Content-Type' => 'application/json',
		'Authorization' => 'Bearer ' . TwitchHelper::getAccessToken(),
	]
]);

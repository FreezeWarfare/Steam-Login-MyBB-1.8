<?php

/**
 * Send to Console - Debug Function
 * ----------------------------------
 */

function debug_to_console( $data ) {

    if ( is_array( $data ) )
        $output = "<script>console.log( 'Debug Objects: " . implode( ',', $data) . "' );</script>";
    else
        $output = "<script>console.log( 'Debug Objects: " . $data . "' );</script>";

    echo $output;
}

/**
 * Steam Login
 * ----------------------------------
 * Provided with no warranties by Ryan Stewart (www.calculator.tf)
 * This has been tested on MyBB 1.6
 */

class steam
{
	
	// You can get an API key by going to http://steamcommunity.com/dev/apikey
	public $API_KEY = "";
	
	function __construct()
	{
		global $db;
		
		$get_key       = $db->fetch_array($db->simple_select("settings", "name, value", "name = 'steamlogin_api_key'"));
		$this->API_KEY = $get_key['value'];
		
		// Check CURL is installed, if not KILL!
		if (!function_exists('curl_version'))
			die("You don't have CURL installed on your server. This is a requirement. Without it, nothing would work...");
		
	} // close function __construct
	
	function curl($url)
	{
		if (function_exists('curl_version'))
		{
			$ch = curl_init();
			curl_setopt_array($ch, array(
				CURLOPT_URL => $url,
				CURLOPT_HEADER => false,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 10
			));
			$data = curl_exec($ch);
			curl_close($ch);
			return $data;
			
		}
		else // if(function_exists('curl_version'))
		{
			
			if (function_exists('fopen') && ini_get('allow_url_fopen'))
			{
				$context = stream_context_create(array(
					'http' => array(
						'timeout' => 10.0
					)
				));
				$handle  = @fopen($url, 'r', false, $context);
				$file    = @stream_get_contents($handle);
				@fclose($handle);
				return $file;
				
			}
			else
			{
				if (!function_exists('fopen') && ini_get('allow_url_fopen'))
				{
					die("cURL and Fopen are both disabled. Please enable one or the other. cURL is prefered.");
				}
				elseif (function_exists('fopen') && !ini_get('allow_url_fopen'))
				{
					die("cURL is disabled and Fopen is enabled but 'allow_url_fopen' is disabled(means you can not open external urls). Please enabled one or the other.");
				}
				else
				{
					die("cURL and Fopen are both disabled. Please enable one or the other. cURL is prefered.");
				}
			}
			
		} // close else
	} // close function curl
	
	// With thanks to https://github.com/damianb/tf2stats for the convert64to32 function.
	function convert64to32($steam_cid)
	{
		$id    = array(
			'STEAM_0'
		);
		$id[1] = substr($steam_cid, -1, 1) % 2 == 0 ? 0 : 1;
		$id[2] = bcsub($steam_cid, '76561197960265728');
		if (bccomp($id[2], '0') != 1)
		{
			return false;
		}
		$id[2] = bcsub($id[2], $id[1]);
		list($id[2], ) = explode('.', bcdiv($id[2], 2), 2);
		return implode(':', $id);
	} // close function convert64to32
	
	/**
	 *
	 * Steam Unique Username - steam_unique_username
	 * - - - - - - - - - - - - - - -
	 * @desc Ensures that Usernames are unique otherwise the db will not accept them
	 * @since 1.8
	 * @version 1.8
	 *
	 */
	function steam_unique_username($personaname, $steamid)
	{

		global $db,$mybb;
				
		//Before we check against database we want to filter the name if enabled
		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;
		$parser_options_filter_only = array(
			'allow_mycode' => 0,
			'allow_smilies' => 0,
			'allow_imgcode' => 0,
			'allow_videocode' => 0,
			'allow_html' => 0,
			'filter_badwords' => intval($mybb->settings['steamlogin_filter_username'])
		);
		//We are running encode inside parse and then decode it again because we do a more complete encode process a bit later.
		$personaname = htmlspecialchars_decode($parser->parse_message($personaname, $parser_options_filter_only));
				
		//Encoding unicode and making sql save.
		$personaname = $this->steam_unicode_username($personaname);
		$personaname = $db->escape_string($personaname);

		//Init some vars
		$i = 0;
		
		//Check for users with my steam name
		//debug_to_console('Sending Username "'.$personaname.'"');
		$returnrows = ($db->simple_select('users', '*', "username = '$personaname'"));
		$f = $db->num_rows($returnrows);
		//debug_to_console($f.' User(s) with this Name.');
		
		//Before we start the look lets see if the result is us
		//debug_to_console('Checking for SteamID "'.$steamid.'"');
		$itisus = $db->simple_select('users', '*', "loginname='$steamid' and username='$personaname'");
		$isit = $db->num_rows($itisus);
		//if($isit != 1){debug_to_console('The user that was found is not this user.');}

		//It isnt us and there is users with that name already
		if($isit == 0 && $f > 0)
		{
			
			//Seems it isnt us so
			$i = 0;
			
			//If it is bigger than 0 there is more than 0 of me
			while($f > 0)
			{
				//Code for name numbering Alt, Alt (2), Alt(3), etc.		
				$tempersona = $personaname.' ('.($i+2).')';
				
				//Check so the loop keeps going if needed to
				$returnrows = ($db->simple_select('users', '*', "username = '$tempersona'"));
				$f = $db->num_rows($returnrows);
				
				//Check if user is me
				$itisus = $db->simple_select('users', '*', "loginname='$steamid' and username='$tempersona'");
				$isit = $db->num_rows($itisus);
				
				//If user is me escape the loop
				if($isit == 1)
				{
					$f = 0;
					//if($isit != 1){debug_to_console('The user that was found is this user.');}
				};
				
				$i++;
			}
			if($f == 0){$personaname = $personaname.' ('.($i+1).')';};
		};
		//Result will always be Unicode and Escaped
		return $personaname;
	}
	
	/**
	 *
	 * Steam Unicode Username - steam_unicode_username
	 * - - - - - - - - - - - - - - -
	 * @desc Ensures unicode chars are converted to html code.
	 * @since 1.8
	 * @version 1.8
	 *
	 */
	 
	// source - http://php.net/manual/en/function.ord.php#109812
	function ordutf8($string, &$offset) {
		$code = ord(substr($string, $offset,1));
		if ($code >= 128) {        //otherwise 0xxxxxxx
			if ($code < 224) $bytesnumber = 2;                //110xxxxx
			else if ($code < 240) $bytesnumber = 3;        //1110xxxx
			else if ($code < 248) $bytesnumber = 4;    //11110xxx
			$codetemp = $code - 192 - ($bytesnumber > 2 ? 32 : 0) - ($bytesnumber > 3 ? 16 : 0);
			for ($i = 2; $i <= $bytesnumber; $i++) {
				$offset ++;
				$code2 = ord(substr($string, $offset, 1)) - 128;        //10xxxxxx
				$codetemp = $codetemp*64 + $code2;
			}
			$code = $codetemp;
		}
		$offset += 1;
		if ($offset >= strlen($string)) $offset = -1;
		return $code;
	}
	
	// source - http://php.net/manual/en/function.chr.php#88611
	function unichrsteam($u) {
		return mb_convert_encoding('&#' . intval($u) . ';', 'UTF-8', 'HTML-ENTITIES');
	}
	
	function steam_unicode_username( $string ) {
		$stringBuilder = "";
		$offset = 0;

		if ( empty( $string ) ) {
			return "";
		}

		while ( $offset >= 0 ) {
			$decValue = $this->ordutf8( $string, $offset );
			$char = $this->unichrsteam($decValue);

			$htmlEntited = $char;
			if( $char != $htmlEntited ){
				$stringBuilder .= $htmlEntited;
			} elseif( $decValue >= 128 ){
				$stringBuilder .= "&#" . $decValue . ";";
			} else {
				$stringBuilder .= $char;
			}
		}
		
		return $stringBuilder;
	}
	
	/**
	 * get_user_info
	 *-------------------------------------
	 * This will return information about the Steam user
	 * including their avatar, persona and online status.
	 */
	function get_user_info($id = '')
	{
		
		// Resolve our ID.
		$id = $this->_resolve_vanity($id);
		
		if ($id['status'] == 'success')
		{
			
			$info_array = $this->curl('http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=' . $this->API_KEY . '&steamids=' . $id['steamid']);
			$info_array = json_decode($info_array, true);
			
			if (isset($info_array['response']['players'][0]))
			{
				
				$player_info = $info_array['response']['players'][0];
				
				//Encoding the username from unicode and escaping it
				$personaname = $this->steam_unicode_username($player_info["personaname"]);
								
				//Hack to prevent commas from breaking pm system
				$personaname = preg_replace("/,/", "，", $personaname);
				
				//Make sure the is no quitespace
				$personaname = trim($personaname);
				
				//Deal with empty usernames
				if ($personaname == '')
				{
					$personaname = 'Temporary Username';
				}
				
				//Namelenght of 1 is minimum, enforcing
				while (strlen($personaname) < 2)
				{
					$personaname = $personaname . '-';
				}
				
				//Run function to ensure username is unique in our database.
				$personaname = $this->steam_unique_username($personaname, $id['steamid']);
				
				$profileurl   = $player_info['profileurl'];
				$avatar_s     = $player_info['avatar'];
				$avatar_m     = $player_info['avatarmedium'];
				$avatar_l     = $player_info['avatarfull'];
				$personastate = $player_info['personastate'];
				
				$steamid32 = $this->convert64to32($id['steamid']);
				
				$return_array = array(
					'status' => 'success',
					'steamid' => $id['steamid'],
					'steamid32' => $steamid32,
					'personaname' => $personaname,
					'profileurl' => $profileurl,
					'avatars' => array(
						'small' => $avatar_s,
						'medium' => $avatar_m,
						'large' => $avatar_l
					),
					'personastate' => $personastate
				);
				
			}
			else
			{
				
				$return_array = array(
					'status' => 'error',
					'message' => 'An error occurred retrieving user information from the Steam service.'
				);
				
			} // close else
			
		}
		elseif ($id['status'] == 'error')
		{
			
			$return_array = array(
				'status' => 'error',
				'message' => $id['message']
			);
			
		} // close elseif($id['status'] == 'error')
		
		return $return_array;
		
	} // close get_user_info
	
	
	function get_steam_level($steamid = 0)
	{
		
		if ($steamid > 0)
		{
			
			// Set a default level as ?, just incase something goes wrong.
			$level = '?';
			
			// Do the CURL request to the Steam service.
			$get_response = $this->curl('http://api.steampowered.com/IPlayerService/GetSteamLevel/v1/?key=' . $this->API_KEY . '&steamid=' . $steamid);
			$get_response = json_decode($get_response);
			
			// Check if the response is telling us the level.
			if (isset($get_response->response->player_level))
				$level = $get_response->response->player_level;
			
			// Finally, return it.
			return $level;
			
		} // close if($steamid > 0)
		
	} // close function get_steam_level
	
	
	/**
	 * _resolve_vanity
	 * -------------------------------------
	 * This can be used to get the Steam 64 ID from a ID (Stewartiee) 
	 * or Steam Link (www.steamcommunity.com/id/Stewartiee)
	 */
	function _resolve_vanity($link = '')
	{
		
		// If the passed value is numeric and 17 characters we presume it's a Steam 64 ID.
		if (is_numeric($link) and strlen($link) == 17)
		{
			
			$return_array = array(
				'status' => 'success',
				'steamid' => $link
			);
			
		}
		else
		{
			
			if (strstr($link, '/'))
			{
				$link         = rtrim($link, '/');
				$explode_link = explode('/', $link);
				$link         = end($explode_link);
			}
			
			$vanity_array = $this->curl('http://api.steampowered.com/ISteamUser/ResolveVanityURL/v0001/?key=' . $this->API_KEY . '&vanityurl=' . $link);
			$vanity_array = json_decode($vanity_array, true);
			
			if ($vanity_array['response']['success'] == 1)
			{
				
				$steamid = $vanity_array['response']['steamid'];
				
				$return_array = array(
					'status' => 'success',
					'steamid' => $steamid
				);
				
			}
			elseif ($vanity_array['response']['success'] == 42)
			{
				
				$message = $vanity_array['response']['message'];
				
				$return_array = array(
					'status' => 'error',
					'message' => $message
				);
				
			} // close elseif($vanity_array['response']['success'] == 42)
			
		} // close else
		
		return $return_array;
		
	} // close function _resolve_vanity
	
} // close class steam
?>

<?php
/*
 * FurAffinity API PHP
 *
 * PHP version 5.4.45
 *
 * @package		FurAffinity-API-PHP
 * @author		d1KdaT <i@d1kdat.ru>
 * @version		1.0.0
 * @license		MIT License
 * @link		https://github.com/d1KdaT/FurAffinity-API-PHP
 */

/*
 * Two classes to describe errors in requests
 */
class TimeOutException extends Exception { }
class BadRespondException extends Exception { }

class FurAffinityAPI
{
	/*
	 * @var string
	 */
	private $username;
	
	/*
	 * @var string
	 */
	private $cookies;
	
	/*
	 * Create an object with the user's cookies: "a" and "b" from FurAffinity
	 *
	 * @param array(username, a, b)
	 *
	 * @throw \RuntimeException - cURL is not installed
	 * @throw \InvalidArgumentException - lost one or more of the parameters
	 */
	public function __construct($settings)
	{
		if(!function_exists('curl_init'))
		{
			throw new RuntimeException("cURL is needed, see: http://curl.haxx.se/docs/install.html");
		}

		if(!isset($settings['username']) || !isset($settings['a']) || !isset($settings['b']))
		{
			throw new InvalidArgumentException("Lost one or more of the settings parameters");
		}

		$cookie = array();
		$this->username = $settings['username'];
		foreach($settings as $k => $v)
		{
			if($k == "username")
			{
				$this->username = $settings['username'];
			}
			else
			{
				$cookie[$k] = $v;
			}
		}
		foreach($cookie as $k => $v)
		{
			$this->cookies[] = $k."=".$v;
		}
		$this->cookies = implode("; ", $this->cookies);
	}
	
	/*
	 * Get information about submission
	 *
	 * @param int
	 *
	 * @return mixed [array(title, author, username, file) or bool]
	 */
	public function getById($id)
	{
		$return = false;
		$response = $this->curl("http://www.furaffinity.net/view/".$id."/");
		if($response)
		{
			if(preg_match("/by \<a href\=\"\/user\/(.+)\/\"\>(.+)\<\/a\>/ui", $response, $match) || preg_match("/<a href=\"\/user\/(.+)\/\"><h2>(.+)<\/h2><\/a>/ui", $response, $match))
			{
				$return['author'] = $match[2];
				$return['username'] = $match[1];
			}
			if($return && (preg_match("/\<b\>\<a href\=\"(.+)\"\>Download\<\/a\>\<\/b\>/ui", $response, $match) || preg_match("/<a class=\"button section-button\" href=\"(.+)\">Download<\/a>/ui", $response, $match)))
			{
				if(preg_match("/^\/\//",$match[1])) $match[1] = "http:".$match[1];
				$return['file'] = $match[1];
			}
			if($return && preg_match("/<meta property=\"og:title\" content=\"(.+) by ".preg_quote($return['author'])."\" \/>/ui", $response, $match))
			{
				$return['title'] = $match[1];
			}
			return $return;
		}
		else
		{
			return false;
		}
	}
	
	/*
	 * Get user watchlist
	 *
	 * @param string
	 *
	 * @return mixed [array(array(username, screen_name)) or bool]
	 */
	public function getWatchlist($username = null)
	{
		if($username == null)
		{
			$username = $this->username;
		}
		$return = false;
		$i = 1;
		while(true)
		{
			$response = $this->curl("http://www.furaffinity.net/watchlist/by/".$username."/".$i."/");
			if(preg_match_all("/<a href=\"\/user\/(.+)\/\" target=\"_blank\"><span class=\"artist_name\">(.+)<\/span><\/a>/ui", $response, $match) || preg_match_all("/<a href=\"\/user\/(.+)\/\" target=\"_blank\">(.+)<\/a>/ui", $response, $match))
			{
				if(count($match[1]) > 0)
				{
					foreach($match[1] as $k => $v)
					{
						$return[] = array(
							"username" => $v,
							"screen_name" => $match[2][$k]);
					}
				}
				else
				{
					break;
				}
			}
			else
			{
				break;
			}
			$i++;
		}
		return $return;
	}
	
	/*
	 * @param string
	 *
	 * @throw \TimeOutException - Server didn't respond
	 *
	 * @return bool
	 */
	public function checkUserExist($username = null)
	{
		if($username == null)
		{
			$username = $this->username;
		}
		$return = false;
		$response = $this->curl("http://www.furaffinity.net/user/".$username."/");

		if(!$response)
		{
			throw new TimeOutException("The server didn't respond for a certain time");
		}

		if($response && !preg_match("/<font face=\'Verdana\' size=1>This user cannot be found\.<br\/>/ui", $response))
		{
			$return = true;
		}
		return $return;
	}
	
	/*
	 * Check (setting username == authorized username)
	 *
	 * @throw \TimeOutException - Server didn't respond
	 * @throw \BadRespondException - Failed to load the content (CloudFlare DDoS Protection)
	 *
	 * @return bool
	 */
	public function checkLogIn()
	{
		$return = false;
		$response = $this->curl("http://www.furaffinity.net/submit/");
		
		if(!$response)
		{
			throw new TimeOutException("The server didn't respond for a certain time");
		}
		
		if($response && (preg_match("/<a id=\"my-username\" href=\"\/user\/(.+)\/\">~(.+)<\/a>/ui", $response, $match) || preg_match("/<a id=\"my-username\" class=\"top-heading hideonmobile\" href=\"\/user\/(.+)\/\">~(.+)<span class=\"hideondesktop\">/ui", $response, $match)))
		{
			if($match[1] == $this->username)
			{
				$return = true;
			}
		}
		elseif($response && !preg_match("/Fur Affinity/ui", $response))
		{
			throw new BadRespondException("The content is not loaded");
		}
		return $return;
	}
	
	/*
	 * @param string
	 *
	 * @return int [0, 1 or 2 (0 - bad respond, 1 - success, 2 - already)]
	 */
	public function addWatch($username)
	{
		$return = 0;
		$response = $this->curl("http://www.furaffinity.net/user/".$username."/");
		if(preg_match("/<b><a href=\"\/watch\/".preg_quote($username)."\/\?key=([A-Za-z0-9]+)\">\+Watch<\/a><\/b>/ui", $response, $match) || preg_match("/<a href=\"\/watch\/".preg_quote($username)."\/\?key=([A-Za-z0-9]+)\"><div class=\"button userpage\-button green hideonmobile\">\+Watch<\/div><\/a>/ui", $response, $match))
		{
			$response = $this->curl("http://www.furaffinity.net/watch/".$username."/?key=".$match[1]);
			if(preg_match("/".preg_quote($username)." has been added to your watch list\!/ui", $response))
			{
				$return = 1;
			}
		}
		elseif(preg_match("/<b><a href=\"\/unwatch\/".preg_quote($username)."\/\?key=([A-Za-z0-9]+)\">\-Watch<\/a><\/b>/ui", $response) || preg_match("/<a href=\"\/unwatch\/".preg_quote($username)."\/\?key=([A-Za-z0-9]+)\"><div class=\"button userpage\-button green hideonmobile\">\-Watch<\/div><\/a>/ui", $response))
		{
			$return = 2;
		}
		return $return;
	}
	
	/*
	 * @param string
	 *
	 * @return int [0, 1 or 2 (0 - bad respond, 1 - success, 2 - already)]
	 */
	public function removeWatch($username)
	{
		$return = 0;
		$response = $this->curl("http://www.furaffinity.net/user/".$username."/");
		if(preg_match("/<b><a href=\"\/unwatch\/".preg_quote($username)."\/\?key=([A-Za-z0-9]+)\">\-Watch<\/a><\/b>/ui", $response, $match) || preg_match("/<a href=\"\/unwatch\/".preg_quote($username)."\/\?key=([A-Za-z0-9]+)\"><div class=\"button userpage\-button green hideonmobile\">\-Watch<\/div><\/a>/ui", $response, $match))
		{
			$response = $this->curl("http://www.furaffinity.net/unwatch/".$username."/?key=".$match[1]);
			if(preg_match("/".preg_quote($username)." has been removed from your watch list\!/ui", $response))
			{
				$return = 1;
			}
		}
		elseif(preg_match("/<b><a href=\"\/watch\/".preg_quote($username)."\/\?key=([A-Za-z0-9]+)\">\+Watch<\/a><\/b>/ui", $response) || preg_match("/<a href=\"\/watch\/".preg_quote($username)."\/\?key=([A-Za-z0-9]+)\"><div class=\"button userpage\-button green hideonmobile\">\+Watch<\/div><\/a>/ui", $response))
		{
			$return = 2;
		}
		return $return;
	}
	
	/*
	 * @param int
	 *
	 * @return int [0, 1 or 2 (0 - bad respond, 1 - success, 2 - already)]
	 */
	public function addFavorite($id)
	{
		$return = 0;
		$response = $this->curl("http://www.furaffinity.net/view/".$id."/");
		if(preg_match("/<b><a href=\"\/fav\/".preg_quote($id)."\/\?key=([A-Za-z0-9]+)\">\+Add to Favorites<\/a><\/b>/ui", $response, $match) || preg_match("/<a class=\"button section-button\" href=\"\/fav\/".preg_quote($id)."\/\?key=([A-Za-z0-9]+)\">\+Fav<\/a>/ui", $response, $match))
		{
			$response = $this->curl("http://www.furaffinity.net/fav/".$id."/?key=".$match[1]);
			if($response)
			{
				$return = 1;
			}
		}
		elseif(preg_match("/<b><a href=\"\/fav\/".preg_quote($id)."\/\?key=([A-Za-z0-9]+)\">\-Remove from Favorites<\/a><\/b>/ui", $response) || preg_match("/<a class=\"button section-button\" href=\"\/fav\/".preg_quote($id)."\/\?key=([A-Za-z0-9]+)\">\-Favs<\/a>/ui", $response))
		{
			$return = 2;
		}
		return $return;
	}
	
	/*
	 * @param int
	 *
	 * @return int [0, 1 or 2 (0 - bad respond, 1 - success, 2 - already)]
	 */
	public function removeFavorite($id)
	{
		$return = 0;
		$response = $this->curl("http://www.furaffinity.net/view/".$id."/");
		if(preg_match("/<b><a href=\"\/fav\/".preg_quote($id)."\/\?key=([A-Za-z0-9]+)\">\-Remove from Favorites<\/a><\/b>/ui", $response, $match) || preg_match("/<a class=\"button section-button\" href=\"\/fav\/".preg_quote($id)."\/\?key=([A-Za-z0-9]+)\">\-Favs<\/a>/ui", $response, $match))
		{
			$response = $this->curl("http://www.furaffinity.net/fav/".$id."/?key=".$match[1]);
			if($response)
			{
				$return = 1;
			}
		}
		elseif(preg_match("/<b><a href=\"\/fav\/".preg_quote($id)."\/\?key=([A-Za-z0-9]+)\">\+Add to Favorites<\/a><\/b>/ui", $response) || preg_match("/<a class=\"button section-button\" href=\"\/fav\/".preg_quote($id)."\/\?key=([A-Za-z0-9]+)\">\+Fav<\/a>/ui", $response))
		{
			$return = 2;
		}
		return $return;
	}

	/*
	 * @param string
	 *
	 * @return mixed (string or bool)
	 */
	private function curl($url)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
		curl_setopt($ch, CURLOPT_TIMEOUT, 3);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT)");
		curl_setopt($ch, CURLOPT_COOKIE, $this->cookies);
		$result = curl_exec($ch);
		
		if (!$result)
		{
			return false;
		}

		curl_close($ch);
		return $result;
	}
}

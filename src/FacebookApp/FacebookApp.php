<?php
/**
 * Created by Edvaldo Szymonek
 * User: edvaldo
 * Date: 13/04/2015
 * Time: 09:46
 * Website: http://edvaldotsi.com
 */

namespace FacebookApp;

use Exception;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookSession;
use Facebook\FacebookSDKException;

use Facebook\GraphUser;
use SplObjectStorage;

class FacebookApp
{
    /**
     * Your Facebook App configuration
     * @var array $config
     */
    private $config;

    /**
     * @var \Facebook\FacebookSession
     */
    private $session;

    public function __construct($config)
    {
        $this->config = $config;
        FacebookSession::setDefaultApplication($this->config["app_id"], $this->config["app_secret"]);
    }

    /**
     * Send request against facebook graph
     *
     * @param string $method
     * @param string $path
     * @param array|null $params
     *
     * @return \Facebook\GraphObject
     *
     * @throws \Facebook\FacebookRequestException
     */
    private function sendRequest($method, $path, $params = null)
    {
        $request = new FacebookRequest($this->session, $method, $path, $params);
        $response = $request->execute();
        return $response->getGraphObject();
    }

    /**
     * Check the access token
     *
     * @param string $token
     * @return bool
     */
    public function checkAccess($token)
    {
        $this->session = new FacebookSession($token);
        try {
            return $this->session->validate();
        } catch(FacebookSDKException $ex) {
            return false;
        }
        return false;
    }

    /**
     * Return the link generated by Facebook to login
     *
     * @return string
     */
    public function getLoginUrl()
    {
        $helper = new FacebookRedirectLoginHelper($this->config["redirect_url"]);
        return $helper->getLoginUrl($this->config["scope"]);
    }

    /**
     * Return the access token generated by login
     *
     * @return string|null
     */
    public function getAccessToken()
    {
        $helper = new FacebookRedirectLoginHelper($this->config["redirect_url"]);
        $session = $helper->getSessionFromRedirect();
        if ($session) {
            return (string) $session->getAccessToken();
        }
        return null;
    }

    /**
     * Publish $post in $target's feed
     *
     * @param Feed $target
     * @param Post $post
     * @param array $params
     *
     * @throws Exception
     *
     * @return Post
     */
    public function publish(Feed $target, Post $post, Array $params = null)
    {
        $params["message"] = $post->getMessage();

        if ($post->getLink() instanceof Link) {
            $link = $post->getLink();
            $params["link"] = (string) $link;
            $params["picture"] = $link->getPicture();
            $params["name"] = $link->getName();
            $params["caption"] = $link->getCaption();
            $params["description"] = $link->getDescription();
        }

        if ($post->getTags() instanceof SplObjectStorage)
            if (empty($post->getPlace())) {
                throw new Exception("You must set an page ID of a location associated with this post");
            } else {
                $params["place"] = $post->getPlace();
                $params["tags"] = $post->getTagsAsString();
            }

        $graph = $this->sendRequest("POST", "/{$target->getId()}/feed", $params);
        $post->setId($graph->getProperty("id"));
        return $post;
    }

    /**
     * Retrieve user's profile
     *
     * @return Profile
     */
    public function getProfile()
    {
        $graph = $this->sendRequest("GET", "/me")->cast(GraphUser::className());
        $profile = new Profile($graph->getProperty("id"));
        $profile->setName($graph->getProperty("name"));
        $profile->setLink($graph->getProperty("link"));
        $profile->setLocale($graph->getProperty("locale"));
        $profile->setApp($this);
        return $profile;
    }

    /**
     * Return a list of user's groups
     *
     * @param Profile $profile
     * @return SplObjectStorage
     */
    public function getGroups(Profile $profile)
    {
        $graph = $this->sendRequest("GET", "/{$profile->getId()}/groups");
        $list = $graph->getPropertyAsArray("data");
        $out = new SplObjectStorage();

        foreach ($list as $data) {
            $group = new Group(
                $data->getProperty("id"),
                $data->getProperty("name"),
                (bool) $data->getProperty("administrator")
            );
            $group->setApp($this);
            $out->attach($group);
        }
        return $out;
    }

    /**
     * Return a list of user's pages
     *
     * @param Profile $profile
     * @return SplObjectStorage
     */
    public function getPages(Profile $profile)
    {
        $graph = $this->sendRequest("GET", "/{$profile->getId()}/accounts", array("access_token"));
        $list = $graph->getPropertyAsArray("data");
        $out = new SplObjectStorage();

        foreach ($list as $data) {
            $page = new Page($data->getProperty("id"));
            $page->setName($data->getProperty("name"));
            $page->setCategory($data->getProperty("category"));
            $page->setAccessToken($data->getProperty("access_token"));
            $page->setApp($this);
            $out->attach($page);
        }
        return $out;
    }
} 
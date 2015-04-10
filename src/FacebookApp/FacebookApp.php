<?php

namespace FacebookApp;

use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookSession;
use Facebook\FacebookSDKException;

use Facebook\GraphUserPage;
use SplObjectStorage;

class FacebookApp
{
    /**
     * Your Facebook App ID
     */
    const APP_ID = "433974716778616";

    /**
     * Your Facebook App Secret
     */
    const APP_SECRET = "124f17ab103b3af0b041db138ed02dce";

    /**
     * Redirect URI after login
     */
    const REDIRECT_URL = "http://facebook-app.localhost/logado.php";

    /**
     * Your app scope
     *
     * @var array $scope
     */
    private $scope = array(
        "publish_actions",
        "manage_pages",
        "user_groups"
    );

    /**
     * @var \Facebook\FacebookSession
     */
    private $session;

    public function __construct()
    {
        FacebookSession::setDefaultApplication(self::APP_ID, self::APP_SECRET);
    }

    /**
     * Send request against facebook graph
     *
     * @param string $method
     * @param string $path
     * @param array|null $params
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
        $helper = new FacebookRedirectLoginHelper(self::REDIRECT_URL);
        return $helper->getLoginUrl($this->scope);
    }

    /**
     * Return the access token generated by login
     *
     * @return string|null
     */
    public function getAccessToken()
    {
        $helper = new FacebookRedirectLoginHelper(self::REDIRECT_URL);
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
     * @return Post
     */
    public function publish(Feed $target, Post $post, Array $params = array())
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

        $graph = $this->sendRequest("POST", "/{$target->getId()}/feed", $params);
        $post->setId($graph->getProperty("id"));
        return $post;
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
            $group = new Group($data->getProperty("id"));
            $group->setApp($this);
            $group->setName($data->getProperty("name"));
            $group->setAdmin((bool) $data->getProperty("administrator"));
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
            $page->setApp($this);
            $page->setName($data->getProperty("name"));
            $page->setCategory($data->getProperty("category"));
            $page->setAccessToken($data->getProperty("access_token"));
            $out->attach($page);
        }
        return $out;
    }
} 
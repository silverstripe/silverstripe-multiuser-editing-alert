<?php

namespace Silverstripe\MultiUserEditing;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Security;
use Psr\SimpleCache\CacheInterface;

class MultiUserEditingController extends Controller implements Flushable
{
    private static $allowed_actions = [
        'set',
        'get',
    ];
    
    private $user = null; //current user DataObject
    protected $usersEditing = null; //array of all users editing
    protected $editingCache = null; //CacheInterface to store all concurrently editing users

    protected function init()
    {
        parent::init();

        //check user login status
        $this->user = Security::getCurrentUser();
        if (!$this->user) {
            return false;
        }

        //get the cache data
        $this->editingCache = Injector::inst()->get(CacheInterface::class . '.multiuserediting');


        $usersEditing = unserialize($this->editingCache->get('editing'));

        //create a new simple PHP object to store user editing data
        if (!$usersEditing) {
            $usersEditing = array();
        }

        $timeout = Config::inst()->get(get_class($this), 'userTimeoutInSeconds');

        //remove any users that have timed out
        foreach ($usersEditing as $id => $user) {
            if (!empty($user['lastEdited']) &&
                strtotime($user['lastEdited']) < strtotime('-'.$timeout.' seconds')) {
                //user has timed out after above number of minutes
                unset($usersEditing[$id]);
            }
        }

        $this->usersEditing = $usersEditing;
    }

    public static function flush()
    {
        $cache = Injector::inst()->get(CacheInterface::class . '.multiuserediting');
        $cache->delete('multiuserediting');
    }

    public function set($request)
    {
        if (!$this->user) {
            return false;
        }

        $pageID = $request->param('ID');

        if (!intval($pageID)) {
            user_error("No page ID provided", E_USER_ERROR);
        }

        $dataArray = array();
        $dataArray['lastEdited'] = date("Y-m-d H:i:s"); //when this was last updated
        $dataArray['abbreviatedName'] = $this->user->FirstName . ' ' . substr($this->user->Surname, 0, 1);
        $dataArray['fullName'] = $this->user->FirstName . ' ' . $this->user->Surname;
        $dataArray['firstName'] = $this->user->FirstName;
        $dataArray['email'] = $this->user->Email;
        $dataArray['pageID'] = $pageID;

        //update the user editing data structure for the current user
        $this->usersEditing[$this->user->ID] = $dataArray;

        //save to the cache
        $this->editingCache->set('editing', serialize($this->usersEditing));

        return $this->get();
    }

    public function get()
    {
        $refresh = array(
            'updateIntervalMultiUser' => Config::inst()->get(get_class($this), 'updateIntervalMultiUser'),
            'updateIntervalSingleUser' => Config::inst()->get(get_class($this), 'updateIntervalSingleUser')
        );
        $this->usersEditing['update'] = $refresh;

        return json_encode($this->usersEditing);
    }
}

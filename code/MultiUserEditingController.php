<?php 

class MultiUserEditingController extends Controller implements Flushable
{

    public static $allowed_actions = array(
        'set',
        'get'
    );
    
    private $user = null; //current user DataObject
    protected $usersEditing = null; //array of all users editing
    protected $editingCache = null; //SS_Cache to store all concurrently editing users

    public function init()
    {
        parent::init();

        //check user login status
        $this->user = Member::currentUser();
        if (!$this->user) {
            user_error("User needs to be logged in access multi-user editing data", E_USER_ERROR);
        }

        //get the cache data
        $this->editingCache = SS_Cache::factory('multiuserediting');
        $usersEditing = unserialize($this->editingCache->load('editing'));

        //create a new simple PHP object to store user editing data
        if(!$usersEditing) {
            $usersEditing = array();
        }
        
        $timeout = Config::inst()->get(get_class($this), 'userTimeoutInSeconds');
        
        //remove any users that have timed out
        foreach($usersEditing as $id => $user) {
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
        SS_Cache::factory('multiuserediting')->clean(Zend_Cache::CLEANING_MODE_ALL);
    }

    public function set($request)
    {
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
        $this->editingCache->save(serialize($this->usersEditing), 'editing');
        
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

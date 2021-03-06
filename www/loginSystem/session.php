<?php
/**
 * Session.php
 * 
 * The Session class is meant to simplify the task of keeping
 * track of logged in users and also guests.
 *
 * Written by: Jpmaster77 a.k.a. The Grandmaster of C++ (GMC)
 * Last Updated: August 19, 2004
 */
include_once("database.php");
//include_once("mailer.php");
//include_once("form.php");

require_once $_SERVER['DOCUMENT_ROOT'].'/core/classes/Position_Log.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/core/classes/Position.php';

class Session
{
   var $member_id;
   var $username;     //Username given on sign-up		DONT USE THIS!
   var $userid;       //Random value generated on current login
   var $accountType;	//type of user from the members table
   var $time;         //Time user was last active (page loaded)
   var $logged_in;    //True if user is logged in, false otherwise
   var $userinfo = array();  //The array holding all user info
   var $url;          //The page url current being viewed
   var $referrer;     //Last recorded site page viewed
   var $isAuthorized;
   var $positions;		//Array of current position ID's
   /**
    * Note: referrer should really only be considered the actual
    * page referrer in process.php, any other time it may be
    * inaccurate.
    */

   /* Class constructor */
   function Session(){
      $this->time = time();
      $this->startSession();
   }

   /**
    * startSession - Performs all the actions necessary to 
    * initialize this session object. Tries to determine if the
    * the user has logged in already, and sets the variables 
    * accordingly. Also takes advantage of this page load to
    * update the active visitors tables.
    */
   function startSession(){
      global $database;  //The database connection
      session_start();   //Tell PHP to start the session

      /* Determine if user is logged in */
      $this->logged_in = $this->checkLogin();

      /**
       * Set guest value to users not logged in, and update
       * active guests table accordingly.
       */
      if(!$this->logged_in){
         $this->username = $_SESSION['username'] = GUEST_NAME;
         $this->accountType = GUEST_LEVEL;
         $database->addActiveGuest($_SERVER['REMOTE_ADDR'], $this->time);
      }
      /* Update users last active timestamp */
      else{
         $database->addActiveUser($this->username, $this->time);
      }
      
      /* Remove inactive visitors from database */
      $database->removeInactiveUsers();
      $database->removeInactiveGuests();
      
      /* Set referrer page */
      if(isset($_SESSION['url'])){
         $this->referrer = $_SESSION['url'];
      }else{
         $this->referrer = "/";
      }

      /* Set current url */
      $this->url = $_SESSION['url'] = $_SERVER['PHP_SELF'];
   }

   /**
    * checkLogin - Checks if the user has already previously
    * logged in, and a session with the user has already been
    * established. Also checks to see if user has been remembered.
    * If so, the database is queried to make sure of the user's 
    * authenticity. Returns true if the user has logged in.
    */
   function checkLogin(){
      global $database;  //The database connection
      /* Check if user has been remembered */
      if(isset($_COOKIE['cookname']) && isset($_COOKIE['cookid'])){
         $this->username = $_SESSION['username'] = $_COOKIE['cookname'];
         $this->userid   = $_SESSION['userid']   = $_COOKIE['cookid'];
      }

      /* Username and userid have been set and not guest */
      if(isset($_SESSION['username']) && isset($_SESSION['userid']) &&
         $_SESSION['username'] != GUEST_NAME){
         /* Confirm that username and userid are valid */
         if($database->confirmUserID($_SESSION['username'], $_SESSION['userid']) != 0){
            /* Variables are incorrect, user not logged in */
            unset($_SESSION['username']);
            unset($_SESSION['userid']);
            return false;
         }

         /* User is logged in, set class variables */
	$this->userinfo  = $database->getUserInfo($_SESSION['username']);
	$member_id = $this->userinfo['ID'];
	$position_log_manager = new Position_Log_Manager();
	$current_positions = $position_log_manager->get_current_positions($member_id);
		 
         
         $this->username  = $this->userinfo['username'];
	$this->member_id  = $member_id;
         $this->userid    = $this->userinfo['userid'];
         $this->accountType = $this->userinfo['accountType'];
	$this->positions = $current_positions;
         return true;
      }
      /* User not logged in */
      else{
         return false;
      }
   }

   /**
    * login - The user has submitted his username and password
    * through the login form, this function checks the authenticity
    * of that information in the database and creates the session.
    * Effectively logging in the user if all goes well.
    */
   function login($subuser, $subpass, $subremember){
      global $database;  //The database and form object
	  $errorMsgs = "";

      /* Username error checking */
      if(!$subuser || strlen($subuser = trim($subuser)) == 0){
         $errorMsgs .= "* Username not entered<br />";
      }
      else
	  {
         /* Check if username is not alphanumeric */
         if(!eregi("^([0-9a-z])*$", $subuser)){
            $errorMsgs .= "* Username not alphanumeric<br />";
         }
      }

      /* Password error checking */
      if(!$subpass){
         $errorMsgs .= "* Password not entered<br />";
      }
	  
	  //echo "made it to password not entered";
	  
      /* Return if form errors exist */
      if($errorMsgs != ""){
		 //errors exist, we need to return now
         return $errorMsgs;
      }
	  
	  //echo "past password not entered";

      /* Checks that username is in database and password is correct */
      $subuser = stripslashes($subuser);
      $result = $database->confirmUserPass($subuser, sha1($subpass));

      /* Check error codes */
      if($result == 1){
         $errorMsgs .= "* Username not found<br />";
      }
      else if($result == 2){
         $errorMsgs .= "* Invalid password<br />";
      }
      
      /* Return if form errors exist */
      if($errorMsgs != ""){
		 //errors exist, we need to return now
         return $errorMsgs;
      }

      /* Username and password correct, register session variables */
      $this->userinfo  = $database->getUserInfo($subuser);
      $this->username  = $_SESSION['username'] = $this->userinfo['username'];
      $this->userid    = $_SESSION['userid']   = $this->generateRandID();
      $this->accountType = $this->userinfo['accountType'];
      $this->member_id = $this->userinfo['ID'];
      
      /* Insert userid into database and update active users table */
      $database->updateUserField($this->username, "userid", $this->userid);
      $database->addActiveUser($this->username, $this->time);
      $database->removeActiveGuest($_SERVER['REMOTE_ADDR']);

      /**
       * This is the cool part: the user has requested that we remember that
       * he's logged in, so we set two cookies. One to hold his username,
       * and one to hold his random value userid. It expires by the time
       * specified in constants.php. Now, next time he comes to our site, we will
       * log him in automatically, but only if he didn't log out before he left.
       */
      if($subremember){
         setcookie("cookname", $this->username, time()+COOKIE_EXPIRE, COOKIE_PATH);
         setcookie("cookid",   $this->userid,   time()+COOKIE_EXPIRE, COOKIE_PATH);
      }

      /* Login completed successfully */
	  //lets redirect to the previous page
      return $errorMsgs;
   }

   /**
    * logout - Gets called when the user wants to be logged out of the
    * website. It deletes any cookies that were stored on the users
    * computer as a result of him wanting to be remembered, and also
    * unsets session variables and demotes his user level to guest.
    */
   function logout(){
      global $database;  //The database connection
      /**
       * Delete cookies - the time must be in the past,
       * so just negate what you added when creating the
       * cookie.
       */
      if(isset($_COOKIE['cookname']) && isset($_COOKIE['cookid'])){
         setcookie("cookname", "", time()-COOKIE_EXPIRE, COOKIE_PATH);
         setcookie("cookid",   "", time()-COOKIE_EXPIRE, COOKIE_PATH);
      }

      /* Unset PHP session variables */
      unset($_SESSION['username']);
      unset($_SESSION['userid']);

      /* Reflect fact that user has logged out */
      $this->logged_in = false;
      
      /**
       * Remove from active users table and add to
       * active guests tables.
       */
      $database->removeActiveUser($this->username);
      $database->addActiveGuest($_SERVER['REMOTE_ADDR'], $this->time);
      
      /* Set user level to guest */
      $this->username  = GUEST_NAME;
      $this->accountType = GUEST_LEVEL;
   }
   
   /**
    * generateRandID - Generates a string made up of randomized
    * letters (lower and upper case) and digits and returns
    * the md5 hash of it to be used as a userid.
    */
   function generateRandID(){
      return md5($this->generateRandStr(16));
   }
   
   /**
    * generateRandStr - Generates a string made up of randomized
    * letters (lower and upper case) and digits, the length
    * is a specified parameter.
    */
   function generateRandStr($length){
      $randstr = "";
      for($i=0; $i<$length; $i++){
         $randnum = mt_rand(0,61);
         if($randnum < 10){
            $randstr .= chr($randnum+48);
         }else if($randnum < 36){
            $randstr .= chr($randnum+55);
         }else{
            $randstr .= chr($randnum+61);
         }
      }
      return $randstr;
   }
   
   function isAuth($authUsers){
	   $authorized = false;
	   $position_manager = new Position_Manager();
	   for($i=0; $i < count($authUsers); $i++){
		   $position = $position_manager->get_position_by_type($authUsers[$i]);
		   if(in_array($position->id, $this->positions)){
			$authorized = true;
		   } else if($authUsers[$i] == 'brother'){
			$authorized = true;
		   } else if($authUsers[$i] == 'public'){
			$authorized = true;
		   }
	  }
	  return $authorized;
   }
   
   function checkAuthType($authUsers) {
	   
	   $this->isAuthorized = $this->isAuth($authUsers);
	   
		//echo 'username: '.$this->username;
		//echo 'isAuthorized value: '.$this->isAuthorized.'<br />';
		
		if ($_SESSION["access"] == "granted" && $this->isAuthorized == true && ($this->username != GUEST_NAME)) {
			return $this->isAuthorized;
		} else if($this->isAuthorized == true && isset($this->url) && ($this->username != GUEST_NAME)){
			return $this->isAuthorized;
			//everything's peachy, no need to do any thing
		} else {
			header("Location: /loginForm.php");
		}
   }
};


/**
 * Initialize session object - This must be initialized before
 * the form object because the form uses session variables,
 * which cannot be accessed unless the session has started.
 */
$session = new Session;

/* Initialize form object */
//$form = new Form;

?>

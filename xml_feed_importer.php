<?php
/*
  Plugin Name: XML Feed Importer
  Description: Restful API XML and activeMQ Streaming Integration into Wordpress Feeds
  Version:     1.0
  Author:      Mahboob ur Rehman
  License:     GPL2
  License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

  #api server
  define ('ENDP', 'http://xml.donbest.com/v2');
  define ('TOKEN', '26vh3yv5!1q__S20');
  define( 'XFI_PATH', plugin_dir_path( __FILE__ ) );
  
  

  class xml_feed_importer{

    private $basePath = ENDP;
    private $token = TOKEN;
    private $paramString = '';
    private $dbClass = '';
    private $response = '';
    private $shortcodes = '';

    public $URL;
    public $XMLRequest;
    public $XMLResponseRaw;
    public $parameters;
    public $XPath;

    public $methodURI = '';
    public $paramsArray = '';

    private $defaultDate = '';//date('-m-j');

    /*results*/
    private $resultsArray = array(); //results get loaded into this array container 

    public function __construct() {
        
        add_action('admin_menu', array( $this, 'add_xml_feed_importer_page' ));
        register_activation_hook( __FILE__, array( $this, 'xml_feed_plugin_activate'));
        register_deactivation_hook( __FILE__, array( $this, 'xml_feed_plugin_deactivate' ));
        add_action('xml_feed_event_hourly', array($this,'xml_feed_event_hourly'));
        add_action('xml_feed_event_3mint', array($this,'xml_feed_event_3mint'));
        add_action('init',array($this,'update_xml_feed_call'));
        add_shortcode('db_league_live_odds', array( $this, 'db_league_live_odds_function' ));
        add_shortcode('db_league_live_score', array( $this, 'db_league_live_score_function' ));
        add_filter('cron_schedules',array( $this, 'custom_cron_schedules' ));
        add_action( 'wp_enqueue_scripts', array($this,'xmlfeed_theme_name_scripts') );

    }
    public function xmlfeed_theme_name_scripts(){
      wp_enqueue_style( 'xmlfeed-css', plugin_dir_url( __FILE__ ) . 'plugin-style.css' );
    }
    public function custom_cron_schedules(){
            if(!isset($schedules["5min"])){
                $schedules["5min"] = array(
                    'interval' => 5*60,
                    'display' => __('Once every 5 minutes'));
            }
            if(!isset($schedules["3min"])){
                $schedules["3min"] = array(
                    'interval' => 3*60,
                    'display' => __('Once every 3 minutes'));
            }
            return $schedules;
    }
    public function update_xml_feed_call(){
      // wp_clear_scheduled_hook('xml_feed_event_hourly');
      // wp_clear_scheduled_hook('xml_feed_event_3mint');
      if ( ! wp_next_scheduled( 'xml_feed_event_hourly' ) ) {
          wp_schedule_event( time(), 'hourly', 'xml_feed_event_hourly' );
      }
      if ( ! wp_next_scheduled( 'xml_feed_event_3mint' ) ) {
          wp_schedule_event( time(), '3min', 'xml_feed_event_3mint' );
      }
      if($_POST){

      }
    }
    public function xml_feed_plugin_activate(){
        global $wpdb;
        /* EVents tables creation */
        $db_events_table = $wpdb->prefix . 'db_events';
        if( $wpdb->get_var( "SHOW TABLES LIKE '".$db_events_table. "'") != $db_events_table ) {
          $create_db_events_table ="CREATE TABLE ".$db_events_table."(                
            `event_id` int(10) NOT NULL DEFAULT '0',
            `location_id` int(10) DEFAULT NULL,
            `grouping_id` int(10) DEFAULT NULL,
            `grouping_name` varchar(250) DEFAULT NULL,
            `league_id` int(10) DEFAULT NULL,
            `start_time` datetime DEFAULT NULL,
            `time_changed` tinyint(1) DEFAULT NULL,
            `preseason_flag` tinyint(1) DEFAULT NULL,
            `neutral_location_flag` tinyint(1) DEFAULT NULL,
            `home_team_name` varchar(64) DEFAULT NULL,
            `home_team_id` int(10) DEFAULT NULL,
            `home_team_rotation` int(10) DEFAULT NULL,
            `away_team_name` varchar(64) DEFAULT NULL,
            `away_team_id` int(10) DEFAULT NULL,
            `away_team_rotation` int(10) DEFAULT NULL,
            `state` varchar(64) DEFAULT NULL,
            `home_pitcher` varchar(64) DEFAULT NULL,
            `home_pitcher_hand` varchar(64) DEFAULT NULL,
            `away_pitcher` varchar(64) DEFAULT NULL,
            `away_pitcher_hand` varchar(64) DEFAULT NULL,
            `type` varchar(64) DEFAULT NULL,
            `sub_type` varchar(64) DEFAULT NULL,
            PRIMARY KEY (`event_id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
          $wpdb->query($create_db_events_table);
        }

        /* Leagues Table creation */

        $db_leagues_table = $wpdb->prefix . 'db_leagues';
        if( $wpdb->get_var( "SHOW TABLES LIKE '".$db_leagues_table. "'") != $db_leagues_table ) {
          $create_db_leagues_table ="CREATE TABLE ".$db_leagues_table."(                
             `league_id` int(10) NOT NULL,
              `sport_id` int(10) DEFAULT NULL,
              `name` varchar(64) NOT NULL,
              `abbreviation` varchar(40) DEFAULT NULL,
              `sport_name` varchar(64) DEFAULT NULL,
              `sport_abbreviation` varchar(40) DEFAULT NULL,
              PRIMARY KEY (`league_id`),
              KEY `sport_league_fk` (`sport_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='a dictionary';";
           $wpdb->query($create_db_leagues_table);
        }

         /* Lines Table creation */

        $db_lines_table = $wpdb->prefix . 'db_lines';
        if( $wpdb->get_var( "SHOW TABLES LIKE '".$db_lines_table. "'") != $db_lines_table ) {
          $create_db_lines_table ="CREATE TABLE ".$db_lines_table."(                
            `line_id` int(10) NOT NULL AUTO_INCREMENT,
            `event_id` int(10) NOT NULL,
            `period_id` int(10) NOT NULL,
            `sportsbook_id` int(10) DEFAULT NULL,
            `timestamp` datetime DEFAULT NULL,
            `away_spread_point` decimal(20,1) DEFAULT NULL,
            `home_spread_point` decimal(20,1) DEFAULT NULL,
            `away_spread_price` decimal(20,1) DEFAULT NULL,
            `home_spread_price` decimal(20,1) DEFAULT NULL,
            `total_point` decimal(20,1) DEFAULT NULL,
            `total_over_price` decimal(20,1) DEFAULT NULL,
            `total_under_price` decimal(20,1) DEFAULT NULL,
            `away_money` decimal(20,1) DEFAULT NULL,
            `home_money` decimal(20,1) DEFAULT NULL,
            `draw_money` decimal(20,1) DEFAULT NULL,
            `line_type` varchar(16) DEFAULT NULL,
            PRIMARY KEY (`line_id`)
          ) ENGINE=MyISAM AUTO_INCREMENT=65 DEFAULT CHARSET=latin1;";
           $wpdb->query($create_db_lines_table);
        }

        /* Period Type Table creation */

        $db_period_type_table = $wpdb->prefix . 'db_period_type';
        if( $wpdb->get_var( "SHOW TABLES LIKE '".$db_period_type_table. "'") != $db_period_type_table ) {
          $create_db_period_type_table ="CREATE TABLE ".$db_period_type_table."(                
             `period_type_id` int(10) NOT NULL,
              `name` varchar(64) DEFAULT NULL COMMENT 'FG,1H,4Q,have a period ''other''',
              `abbreviation` varchar(40) DEFAULT NULL,
              `alias` varchar(40) DEFAULT NULL,
              PRIMARY KEY (`period_type_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
           $wpdb->query($create_db_period_type_table);
        }


        /* Score Table creation */

        $db_score_table = $wpdb->prefix . 'db_score';
        if( $wpdb->get_var( "SHOW TABLES LIKE '".$db_score_table. "'") != $db_score_table ) {
          $create_db_score_table ="CREATE TABLE ".$db_score_table."(                
             `score_id` int(20) NOT NULL AUTO_INCREMENT,
              `event_id` int(20) NOT NULL,
              `league_id` int(10) DEFAULT NULL,
              `away_rotation_id` int(10) DEFAULT NULL,
              `home_rotation_id` int(10) DEFAULT NULL,
              `home_score` int(10) DEFAULT NULL,
              `away_score` int(10) DEFAULT NULL,
              `sequence` int(10) DEFAULT NULL,
              `description` varchar(128) DEFAULT NULL COMMENT '1 inning',
              `period_id` int(20) DEFAULT NULL,
              `period_name` varchar(60) DEFAULT NULL,
              `timestamp` datetime NOT NULL,
              `final_flag` tinyint(1) DEFAULT '0',
              PRIMARY KEY (`score_id`),
              KEY `event_score_fk` (`event_id`)
            ) ENGINE=MyISAM AUTO_INCREMENT=59 DEFAULT CHARSET=utf8;";
           $wpdb->query($create_db_score_table);
        }

         /* Sportsbook Table creation */

        $db_sportsbook_table = $wpdb->prefix . 'db_sportsbook';
        if( $wpdb->get_var( "SHOW TABLES LIKE '".$db_sportsbook_table. "'") != $db_sportsbook_table ) {
          $create_db_sportsbook_table ="CREATE TABLE ".$db_sportsbook_table."(                
              `sportsbook_id` int(19) NOT NULL,
              `abbreviation` varchar(64) DEFAULT NULL,
              `name` varchar(128) DEFAULT NULL,
              PRIMARY KEY (`sportsbook_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
           $wpdb->query($create_db_sportsbook_table);
        }

        /* Teams Table creation */

        $db_team_table = $wpdb->prefix . 'db_team';
        if( $wpdb->get_var( "SHOW TABLES LIKE '".$db_team_table. "'") != $db_team_table ) {
          $create_db_team_table ="CREATE TABLE ".$db_team_table."(                
                `team_id` int(10) NOT NULL DEFAULT '0' COMMENT 'less than 100 team_id reserved for legacy proposition',
                `name` varchar(64) DEFAULT NULL,
                `abbreviation` varchar(40) DEFAULT NULL,
                `full_name` varchar(100) DEFAULT NULL,
                `league_id` int(10) DEFAULT NULL,
                PRIMARY KEY (`team_id`)
              ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='we treat proposition_participant as a team with special rang';";
           $wpdb->query($create_db_team_table);
        }


        if ( ! wp_next_scheduled( 'xml_feed_event' ) ) {
          wp_schedule_event( time(), 'hourly', 'xml_feed_event_hourly' );
        }

        if ( ! wp_next_scheduled( 'xml_feed_event_3mint' ) ) {
          wp_schedule_event( time(), '3min', 'xml_feed_event_3mint' );
      }
      

    }

    public function xml_feed_plugin_deactivate(){

      wp_clear_scheduled_hook('xml_feed_event_hourly');
      wp_clear_scheduled_hook('xml_feed_event_3mint');

    }

    public function xml_feed_event_3mint() {

            $this->paramsArray = array('version'=>'1.1');
            $this->methodURI = '/schedule';
            $this->getSchedule();
           
            $this->methodURI = '/event_state';
            $this->getEventState();

            $this->methodURI = '/league';
            $this->getLeague();

            $this->methodURI = '/odds/1';
            $this->getOdds();

            $this->methodURI = '/open/1';
            $this->getOdds();

            //$this->methodURI = '/close/1';
            //$this->getOdds();


            $this->methodURI = '/odds/3';
            $this->getOdds();

            $this->methodURI = '/open/3';
            $this->getOdds();

            //$this->methodURI = '/close/3';
            //$this->getOdds();


            $this->methodURI = '/odds/5';
            $this->getOdds();

            $this->methodURI = '/open/5';
            $this->getOdds();

            //$this->methodURI = '/close/5';
            //$this->getOdds();


            $this->methodURI = '/odds/7';
            $this->getOdds();

            $this->methodURI = '/open/7';
            $this->getOdds();

            //$this->methodURI = '/close/7';
            //$this->getOdds();

            $this->methodURI = '/score';
            $this->getScore();

            $this->methodURI = '/sportsbook';
            $this->getSportsbook();

             $this->methodURI = '/team';
            $this->getTeam();

    }

    public function xml_feed_event_hourly() {
            $this->paramsArray = array('version'=>'1.1');
            $this->methodURI = '/schedule';
            $this->getSchedule();
           
            $this->methodURI = '/event_state';
            $this->getEventState();

            $this->methodURI = '/league';
            $this->getLeague();


            $this->methodURI = '/odds/1';
            $this->getOdds();

            $this->methodURI = '/open/1';
            $this->getOdds();

            //$this->methodURI = '/close/1';
            //$this->getOdds();


            $this->methodURI = '/odds/3';
            $this->getOdds();

            $this->methodURI = '/open/3';
            $this->getOdds();

            //$this->methodURI = '/close/3';
            //$this->getOdds();


            $this->methodURI = '/odds/5';
            $this->getOdds();

            $this->methodURI = '/open/5';
            $this->getOdds();

            //$this->methodURI = '/close/5';
            //$this->getOdds();


            $this->methodURI = '/odds/7';
            $this->getOdds();

            $this->methodURI = '/open/7';
            $this->getOdds();

            //$this->methodURI = '/close/7';
            //$this->getOdds();


            $this->methodURI = '/score';
            $this->getScore();

            $this->methodURI = '/sportsbook';
            $this->getSportsbook();

             $this->methodURI = '/team';
            $this->getTeam();

    }


    public function add_xml_feed_importer_page() {

        add_menu_page('update XML Feed', 'update XML Feed', 'manage_options', 'update_xml_feed', array( $this, 'update_xml_feed_display' ),'dashicons-media-document');
    }

    public function update_xml_feed_display() {
        $this->paramsArray = array('version'=>'1.1');
        if($_POST['submit-btn'] && $_POST['submit-btn']=='add/update events'){

            $this->methodURI = '/schedule';
            $this->getSchedule();
           
            $this->methodURI = '/event_state';
            $this->getEventState();
           

            echo "Events added/updated successfully";

        }

        if($_POST['submit-btn'] && $_POST['submit-btn']=='add/update leagues'){

            $this->methodURI = '/league';
            $this->getLeague();

            echo "leagues added/updated successfully";

        }

        if($_POST['submit-btn'] && $_POST['submit-btn']=='add/update odds'){

            $this->methodURI = '/odds/'.$_POST['league_id'];
            $this->getOdds();

            $this->methodURI = '/open/'.$_POST['league_id'];
            $this->getOdds();

            //$this->methodURI = '/close/'.$_POST['league_id'];
            //$this->getOdds();

            echo "Odds added/updated successfully";

        }

        if($_POST['submit-btn'] && $_POST['submit-btn']=='add/update score'){

          $this->methodURI = '/score';
          $this->getScore();

          echo "Score added/updated successfully";

        }

        if($_POST['submit-btn'] && $_POST['submit-btn']=='add/update sportsbook'){

           $this->methodURI = '/sportsbook';
            $this->getSportsbook();

            echo "Sportsbook added/updated successfully";

        }

        if($_POST['submit-btn'] && $_POST['submit-btn']=='add/update teams'){

           $this->methodURI = '/team';
           $this->getTeam();

            echo "Teams added/updated successfully";

        }

     
     ?>
      <h1>Update XML feed</h1>
      <form method="POST" name="xml-form" action="" enctype="multipart/form-data" >
          <fieldset>
                <input type="submit" value="add/update events" name="submit-btn">
          </fieldset>
        
      </form>
      <br><br>
      <form method="POST" name="xml-form" action="" enctype="multipart/form-data" >
          <input type="submit" value="add/update leagues" name="submit-btn">
      </form>
       <br><br>
      <form method="POST" name="xml-form" action="" enctype="multipart/form-data" >
          <label>League ID i.e 1,3,5,7 <br> Enter only one value: </label><input id="league_id" type="number" name="league_id"><br>
          <input type="submit" value="add/update odds" name="submit-btn">
      </form>
       <br><br>
      <form method="POST" name="xml-form" action="" enctype="multipart/form-data" >
          <input type="submit" value="add/update score" name="submit-btn">
      </form>
       <br><br>
      <form method="POST" name="xml-form" action="" enctype="multipart/form-data" >
          <input type="submit" value="add/update sportsbook" name="submit-btn">
      </form>
       <br><br>
      <form method="POST" name="xml-form" action="" enctype="multipart/form-data" >
          <input type="submit" value="add/update teams" name="submit-btn">
      </form>
      <?php

  }

  /* API functions */

  private function buildParams(){
    
    if(count($this->paramsArray)>0){
      foreach($this->paramsArray as $key=>$value){
        $this->paramString .= '&' . $key . "=" . $value;  
      }
      return $this->paramString;
    }else{
      return -1;
    } 
    
  }
 
  public function getSchedule(){
    global $wpdb;
    $this->response = $this->makeRequest();
    $result =  $this->response->xpath('//league'); 
    /*echo "<pre>";
    print_r($result);
    echo "</pre>";
    exit();*/
    foreach($result as $leagueEventContent){

      $leagueAttribs = $leagueEventContent->attributes();
      $league_id = (int) $leagueAttribs['id'];
        
      foreach($leagueEventContent->group as $groupContent){
          $groupAttribs = $groupContent->attributes();
          $grouping_id = (int)$groupAttribs['id'];
          $group_text = $groupAttribs['name']; 
          $event_type_id = $this->checkGameType($group_text);
            foreach($groupContent->event as $event){
                #event attributes
                $eventAttribs = $event->attributes();
                $event_id = (int)$eventAttribs['id'];
                $season = (string)$eventAttribs['season'];
                $preseason_flag = ($season=='REGULAR')? 0 : 1; 
                $start_time = (string)$eventAttribs['date'];
                //$start_time = $this->convertTime(date('Y-m-d h:i:s',strtotime($start_time)));
                
                $event_type = (string) $event->event_type;
                $event_state = (string) $event->event_state;
                $time_changed = (string) $event->time_changed;
                //$time_changed = $this->convertTime(date('Y-m-d h:i:s',strtotime($time_changed)));
                $neutral = (string) $event->neutral;
              
                $locationAttribs = $event->location->attributes();
                $location_id = (int)$locationAttribs['id'];
                $participantArray = array();
                #get participants
                
                foreach($event->participant as $participantNode){
                  $participantAttribs = $participantNode->attributes();
                    $side = $participantAttribs['side'];
                    $rot = $participantAttribs['rot'];
                  if(isset($participantNode->team)){
                    $teamAttribs = $participantNode->team->attributes();
                    $team_id = $teamAttribs['id'];
                    $team_name = $teamAttribs['name'];
                      $participantArray[(string)$side] = array('rotation'=>(int)$rot, 'team_id'=>(int)$team_id, 'team_name'=>(string)$team_name); 
                  }else{
                    $teamAttribs = $participantNode->attributes();
                    $team_id = 0;
                    $team_name = $teamAttribs['name'];
                    $participantArray[(string)$side] = array('rotation'=>(int)$rot, 'team_id'=>(int)$team_id, 'team_name'=>(string)$team_name);
                    $neutral='true';
                  }

                  ##check for pitchers <pitcher hand="LEFT" id="97001">NATIONALS</pitcher>
                  if(isset($participantNode->pitcher)){
                    $participantArray[(string)$side]['pitcher'] = (string)$participantNode->pitcher;
                    $pitcherAttribs = $participantNode->pitcher->attributes();
                    $participantArray[(string)$side]['pitcher_hand'] = (string)$pitcherAttribs['hand'];
                  }else{
                    $participantArray[(string)$side]['pitcher'] = '';
                    $participantArray[(string)$side]['pitcher_hand'] = '';
                  }

                }
                
                $exist = $wpdb->get_var("SELECT event_id from wp_db_events WHERE event_id=".$event_id);
                $leagues_array = array(1,3,5,7);
                $current_date  = date('Y-m-d');
                $event_date    = date('Y-m-d',strtotime($start_time));
                $current_time  = strtotime($current_date)-60*60*24;
                $event_time    = strtotime($event_date);
                

                if(!$exist && in_array($league_id, $leagues_array) && $event_time>=$current_time && !empty($participantArray['HOME']['team_id']) && !empty($participantArray['AWAY']['team_id'])){
                      
                      if($league_id ==5 && ($participantArray['AWAY']['pitcher']=='' || $participantArray['HOME']['pitcher']=='')){
                          continue;
                      }

                       $eventArray = array(
                      'event_id'=>$event_id,
                      'location_id'=>$location_id,
                      'grouping_id'=>$grouping_id,
                      'grouping_name'=>$group_text,
                      'league_id'=>$league_id,
                      'start_time'=>$start_time, 
                      'time_changed'=>$time_changed, 
                      'preseason_flag'=>$preseason_flag,
                      'neutral'=>$neutral,
                      'home_team_name'=>addslashes($participantArray['HOME']['team_name']),
                      'home_team_id'=>$participantArray['HOME']['team_id'],
                      'home_team_rot'=>$participantArray['HOME']['rotation'],
                      'away_team_name'=>addslashes($participantArray['AWAY']['team_name']),
                      'away_team_id'=>$participantArray['AWAY']['team_id'],
                      'away_team_rot'=>$participantArray['AWAY']['rotation'],
                      'state'=>strtoupper($event_state),
                      'type'=>strtoupper($event_type),
                      'type_name'=>$event_type_id,
                      'away_pitcher'=>$participantArray['AWAY']['pitcher'],
                      'away_pitcher_hand'=>$participantArray['AWAY']['pitcher_hand'],
                      'home_pitcher'=>$participantArray['HOME']['pitcher'],
                      'home_pitcher_hand'=>$participantArray['HOME']['pitcher_hand']
                    );
                    
                    $this->addEvent($eventArray);
                }
               
              }
        }
    }

  }
  
  public function getOdds(){
    global $wpdb;
    $previous = false;
    $this->response = $this->makeRequest();
    $result =  $this->response;//->xpath('//event');
    
    /*echo "<pre>";
    print_r($result);
    echo "</pre>";*/
    
    
    foreach($result as $event){
      $eventAttribs = $event->attributes();
      $event_id = (int) $eventAttribs['id'];

    # DEFAULTS  
      $pointSpreadArray = array(
                'away_spread'=> 0.0,
                'away_price'=> 0.0,
                'home_spread'=> 0.0,
                'home_price'=> 0.0
                );

      $ttlArray = array('total'=> 0.0,
                 'over_price'=> 0.0,
                 'under_price'=> 0.0);

      $moneyArray = array('away_money'=> 0.0,
                'home_money'=> 0.0,
                'draw_money'=> 0.0);

      
      foreach($event->line as $line){
      
        $lineAttribs = $line->attributes();
        //filter previous lines
        if($previous == false && $lineAttribs['type']=='previous'){
          continue;
        }
        
        $line_period = (string) $lineAttribs['period'];   
        $line_period_id = (int) $lineAttribs['period_id'];
        $line_sportsbook = (int) $lineAttribs['sportsbook'];
        $time = (string) $lineAttribs['time'];
        
          if(isset($line->ps)){
            $psAttribs = $line->ps->attributes();
            $pointSpreadArray = array(
                        'away_spread'=> (double) $psAttribs['away_spread'],
                        'away_price'=> (double) $psAttribs['away_price'],
                        'home_spread'=> (double) $psAttribs['home_spread'],
                        'home_price'=> (double) $psAttribs['home_price']
                        );
          }
          
          
          if(isset($line->total)){
            $ttlAttribs = $line->total->attributes();
            $ttlArray = array(
                      'total'=>  (double) $ttlAttribs['total'],
                      'over_price'=> (double) $ttlAttribs['over_price'],
                      'under_price'=>  (double) $ttlAttribs['under_price']
                      );
          }
          
          
          if(isset($line->money)){
            $moneyAttribs = $line->money->attributes();
            $moneyArray = array(
                      'away_money'=> (double) $moneyAttribs['away_money'],
                      'home_money'=> (double) $moneyAttribs['home_money'],
                      'draw_money'=> (double) $moneyAttribs['draw_money']
                      );
          }
          $converted_time = $this->convertTime($time);
          $exist = $wpdb->get_var("SELECT line_id FROM wp_db_lines WHERE event_id=".$event_id." AND sportsbook_id=".$line_sportsbook." AND timestamp='".$converted_time."' AND line_type='".$lineAttribs['type']."'");
          //echo "<br>";
          $sportsbook_array = array(92,119,139);
          $current_date  = date('Y-m-d');
          $line_date    = date('Y-m-d',strtotime($converted_time));
          $current_time  = strtotime($current_date)-60*60*24;
          //echo "<br>";

          //echo "<br>";
          $line_time    = strtotime($line_date);
          //echo "<br>";

         // echo $event_id;
           //echo "<br>";

          //exit();
          if(!$exist && in_array($line_sportsbook, $sportsbook_array) && $line_period_id ==1 && $line_time>=$current_time){
            $eventLineArray = array('event_id'=>$event_id,
                                      'period'=>$line_period,
                                      'period_id'=>$line_period_id,
                                      'sportsbook'=>$line_sportsbook,
                                      'time'=>$converted_time,
                                      'ps'=>$pointSpreadArray,
                                      'total'=>$ttlArray,
                                      'money'=>$moneyArray,
                                      'type'=>$lineAttribs['type']
                                  );
                      
            $this->addLine($eventLineArray);         
          }
             
      }
    }
  }
  
  
  public function getScore(){
    global $wpdb;
    $this->response = $this->makeRequest();
    $result =  $this->response->xpath('//event');
   /* echo "<pre>";
    print_r($result);
    echo "</pre>";
    exit();*/
    foreach($result as $eventScore){
  
      $scoreAttribs = $eventScore->attributes();
      $event_id = (int) $scoreAttribs['id'];
      $league_id = (int) $scoreAttribs['league_id'];
      $home_rot = (int) $scoreAttribs['home_rot'];
      $away_rot = (int) $scoreAttribs['away_rot'];

      $currentScoreAttribs = $eventScore->current_score->attributes();
      $leagues_array = array(1,3,5,7);
      $current_date  = date('Y-m-d');
      
      //$score_date    = date('Y-m-d',strtotime($score_date));
      $current_time  = strtotime($current_date)-60*60*24;


     //echo "Score time:".$score_time."<br>";
     //echo "Current time:".$current_time;

      if(isset($eventScore->period_summary)){

        foreach ($eventScore->period_summary->period as  $period) {
            $periodAttribs = $period->attributes();
            $score_home = $period->score[0]->attributes();
            $score_away = $period->score[1]->attributes();
            $finalFlag = ((string)$periodAttribs['name']=='FINAL')? 1 : 0;
            $score_date    = $this->convertTime($periodAttribs['time']);
            //$score_date    = date('Y-m-d',strtotime($score_date));
            $score_time    = strtotime($score_date);
            $exist = $wpdb->get_var("SELECT event_id FROM wp_db_score WHERE event_id=".$event_id." AND league_id=".$league_id." AND period_id=".$periodAttribs['period_id']." AND timestamp='".$score_date."'");
            if(!$exist && in_array($league_id, $leagues_array) && $score_time>=$current_time){
                  $scorePeriodArray = array(
                    'event_id'=>$event_id,
                    'league_id'=>$league_id,
                    'home_rot'=>$score_home['rot'],
                    'away_rot'=>$score_away['rot'],
                    'away_score'=>(int)$score_away['value'],
                    'home_score'=>(int)$score_home['value'],
                    'description'=>(string)$periodAttribs['description'],
                    'period_id'=>(string)$periodAttribs['period_id'],
                    'time'=>(string)$periodAttribs['time'],
                    'period'=>(string)$periodAttribs['name'],
                    'final'=>$finalFlag
                  );
                  
                  $this->addScore($scorePeriodArray);
            }
        }

      }
      $score_date    = $this->convertTime($currentScoreAttribs['time']);
      $score_time    = strtotime($score_date);
      $finalFlag = ((string)$currentScoreAttribs['period']=='FINAL')? 1 : 0;
      $exist = $wpdb->get_var("SELECT event_id FROM wp_db_score WHERE event_id=".$event_id." AND period_id=".$currentScoreAttribs['period_id']." AND league_id=".$league_id." AND timestamp='".$score_date."'");
      if(!$exist && in_array($league_id, $leagues_array) && $finalFlag==1 && $score_time>=$current_time){
            $scoreArray = array(
              'event_id'=>$event_id,
              'league_id'=>$league_id,
              'home_rot'=>$home_rot,
              'away_rot'=>$away_rot,
              'away_score'=>(int)$currentScoreAttribs['away_score'],
              'home_score'=>(int)$currentScoreAttribs['home_score'],
              'description'=>(string)$currentScoreAttribs['description'],
              'period_id'=>(string)$currentScoreAttribs['period_id'],
              'time'=>(string)$currentScoreAttribs['time'],
              'period'=>(string)$currentScoreAttribs['period'],
              'final'=>$finalFlag
            );
            
            $this->addScore($scoreArray);
      }
    }
  }

  public function getSportsbook(){
    global $wpdb;
    $this->response = $this->makeRequest();
    $result =  $this->response->xpath('//sportsBook');
    foreach($result as $sportsbook){
      $sportsbookAttribs = $sportsbook->attributes();
      $sportsbook_id = $sportsbookAttribs['id'];
      $sports_book_name = (string) $sportsbook->name;
      $sports_book_abbreviation = (string) $sportsbook->abbreviation[0];
      $finalFlag = ((string)$currentScoreAttribs['period']=='FINAL')? 1 : 0;
      $sportsbook_array = array(92,119,139);
      $exist = $wpdb->get_var("SELECT id FROM wp_db_sportsbook WHERE event_id=".$event_id." AND sportsbook_id=".$sportsbook_id);
      if(!$exist && in_array($sportsbook_id, $sportsbook_array)){
        $sportsbookArray = array('id'=>$sportsbook_id,
                     'name'=>addslashes($sports_book_name),
                     'abbreviation'=>$sports_book_abbreviation
                    );
                    
        $this->addSportsbook($sportsbookArray);
      } 
    }
  }
  
  public function getLeague(){
      global $wpdb;
      $this->response = $this->makeRequest();
      $result =  $this->response->xpath('//league');
      /*echo "<pre>";
      print_r($result);
      echo "</pre>";*/
     
      foreach($result as $league){
        $leagueAttribs = $league->attributes();
        $league_id = $leagueAttribs['id'];
        
        $league_name = (string) $league->name;
        $league_abbreviation = (string) $league->abbreviation;
        
        $sportAttribs = $league->sport->attributes();
        $sport_id = $sportAttribs['id'];
        $sport_name = $league->sport->name;
        $sport_abbreviation = $league->sport->abbreviation;
        $leagues_array = array(1,3,5,7);
        $exist = $wpdb->get_var("SELECT league_id from wp_db_leagues WHERE league_id=".$league_id);
        if(!$exist && in_array($league_id, $leagues_array)){
          $leagueArray = array('id'=>(int)$league_id,
                       'name'=>(string)$league_name,
                       'abbreviation'=>(string)$league_abbreviation,
                       'sport_id'=>(int)$sport_id,
                       'sport_name'=>(string)$sport_name,
                       'sport_abbreviation'=>(string)$sport_abbreviation
                      );
                      
          $this->addLeague($leagueArray);
        }
         
      }
  }
  
  public function getEventState(){
    global $wpdb;
    $prefix = $wpdb->prefix;
    $this->response = $this->makeRequest();
    $result =  $this->response->xpath('//event');

    foreach($result as $event){
        #get event attributes
        $eventAttribs = $event->attributes();
        $event_id = (string)$eventAttribs['id'];
        $away_rot = (string)$eventAttribs['away_rot'];
        $event_exist = $wpdb->get_var("SELECT event_id FROM ".$prefix."db_events WHERE event_id=".$event_id);
        if($away_rot=='' || !$event_exist){
          continue;
        }
        foreach($event->state as $event_state_item){
          
          #get state attribs
          $stateAttribs = $event_state_item->attributes();

          $state_id = (string)$stateAttribs['id'];
          $time_stamp = (string)$stateAttribs['time'];
          $original_date = (string)$event_state_item->original_date;
          $current_date = (string)$event_state_item->event_date;
          $name = (string)$event_state_item->name;

          $this->updateEventState(
            array('event_id'=>$event_id,
              'away_rot'=>$away_rot,
              'state_id'=>$state_id,
              'time_stamp'=>$time_stamp,
              'orginal_date'=>$original_date,
              'current_date'=>$current_date,
              'name'=>$name
              )
            );

        }
        
    }

  }

  public function getTeam(){
    global $wpdb;
    $this->response = $this->makeRequest();
    $result = $this->response->xpath('//league');

    foreach($result as $teamLeague){
      $leagueAttribs = $teamLeague->attributes();
      $league_id = $leagueAttribs['id'];
      
      
      foreach($teamLeague->team as $teamNode){
        $teamAttribs = $teamNode->attributes();
        $team_id = $teamAttribs['id'];
        $team_name = $teamNode->name;
        $team_full_name = $teamNode->full_name;
        $team_abbreviation = $teamNode->abbreviation;
        $leagues_array = array(1,3,5,7);
        $exist = $wpdb->get_var("SELECT league_id from wp_db_team WHERE team_id=".$team_id);
        if(!$exist && in_array($league_id, $leagues_array)){
          $teamArray = array('id'=>(int)$team_id,
                     'name'=>addslashes((string)$team_name),
                     'abbreviation'=>(string)$team_abbreviation,
                     'full_name'=>addslashes((string)$team_full_name),
                     'league_id'=>(int)$league_id
                    );
                    
          $this->addteam($teamArray);    
        }       
      }         
      
    }
  }
  
  #UTILS 
  #generic curl wrapper
  public function makeRequest(){
    $parameters = array(); #empty post params
    $paramsString = $this->buildParams();
    $URL = $this->basePath. $this->methodURI .'/?token='.$this->token;
    //test for get variables
    $request = ($paramsString > -1)? $paramsString: ''; 
    $response = $this->pullXML($URL,$request ,$parameters);    
    return $response;
  }
  /* Get Data Functions */
  public function curlRequest() {
  
       $ch=curl_init();
       curl_setopt($ch, CURLOPT_URL, $this->URL.$this->XMLRequest);
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
       $data=curl_exec($ch);
       curl_close($ch);
       return($data);
  }

  /* Get XML Functions */

  public function pullXML($URL, $request, $parameters) {
       $this->URL = $URL;
       $this->XMLRequest = $request;
       $this->parameters = $parameters;
       $this->getFeed();
       $this->simpleXML = simplexml_load_string($this->XMLResponseRaw);
       return $this->simpleXML;
   }

  public function getFeed() {
         $rawData=$this->curlRequest();
         if ($rawData!=-1) {
             $this->XMLResponseRaw=$rawData;
         }
     }

  public function parseXPath() {
       if ($this->XPath!='') {
           $this->XMLXPath=$this->simpleXML->xpath($this->XPath);
           $a=0;
           if (isset($this->XMLXPath[$a])) {
               $XMLParse = parseSimpleXMLData($this->XMLXPath);
           } else {
               $XMLParse=-1;
           }
           return($XMLParse);
       } else {
           $XMLParse = $this->parseSimpleXMLData($this->simpleXML->DATA);
       }
       if (isset($XMLParse)) {
           return($XMLParse);
       } else {
           return(-1);
       }
   }

   public function parseSimpleXMLData($data) {
       $i=0;
       while (isset($data[$i])) {
           foreach($data[$i]->attributes() as $attrib => $value) {
               $XMLParse[$a][$attrib]=$value;
           }
           $i++;
       }

       return($XMLParse);
   }

    /* XML Datawrapper functions */

    /*
    Desc: escapes characters to be mysql ready
    Param: string
    returns: string 
    */

    public function escape($string) {
        if(get_magic_quotes_runtime()) $string = stripslashes($string);
        return @mysql_real_escape_string($string,$this->link_id);
    }


    /*
    Desc: executes SQL query to an open connection
    Param: (MySQL query) to execute
    returns: (query_id) for fetching results etc
    ## add error handel for failed connection
    */

    public function _query($sql) {
      global $wpdb;
    //clear the results array;
      /*echo $sql;
      exit();*/
      $this->resultsArray = array();
      $result = $wpdb->query($sql);
      $wpdb->print_error();
      if (!$result) {
          $wpdb->print_error();
      }
      
     /* while($row = mysql_fetch_assoc($result))
      {
          $this->resultsArray[] = $row;
      }
      
        return $this->resultsArray;*/
    }


    public function addEvent($eventArray){
      global $wpdb;
      $prefix = $wpdb->prefix;
      $query="INSERT INTO ".$prefix."db_events( event_id, 
          location_id, 
          grouping_id, 
          grouping_name, 
          league_id, 
          start_time, 
          time_changed, 
          preseason_flag, 
          neutral_location_flag, 
          home_team_name, 
          home_team_id, 
          home_team_rotation, 
          away_team_name, 
          away_team_id, 
          away_team_rotation, 
          state, 
          away_pitcher, 
          away_pitcher_hand, 
          home_pitcher, 
          home_pitcher_hand, 
          type, 
          sub_type 
        ) values (
        {$eventArray['event_id']}, 
        {$eventArray['location_id']}, 
        {$eventArray['grouping_id']}, 
        '{$eventArray['grouping_name']}', 
        {$eventArray['league_id']}, 
        '{$eventArray['start_time']}', 
        {$eventArray['time_changed']}, 
        {$eventArray['preseason_flag']}, 
        {$eventArray['neutral']},
        '{$eventArray['home_team_name']}',
        {$eventArray['home_team_id']},
        {$eventArray['home_team_rot']},
        '{$eventArray['away_team_name']}',
        {$eventArray['away_team_id']},
        {$eventArray['away_team_rot']},
        '{$eventArray['state']}',
        '{$eventArray['away_pitcher']}',
        '{$eventArray['away_pitcher_hand']}',
        '{$eventArray['home_pitcher']}',
        '{$eventArray['home_pitcher_hand']}',
        '{$eventArray['type']}',
        '{$eventArray['type_name']}'
        );";

        return $this->_query($query);
    }


    public function addLine($lineArray){
      global $wpdb;
      $prefix = $wpdb->prefix;
      ##see what type of line it is
      #see if its timestamp is greater than the current line
      $time = $this->convertTime($lineArray['time']);

      ## check to see if the line already exists - this can be accomplished with database restrictions or other methods..
      $selectquery="select count(*) from ".$prefix."db_lines where timestamp = '{$time}' and sportsbook_id={$lineArray['sportsbook']} and event_id = {$lineArray['event_id']};";

      $test = $wpdb->get_var($selectquery);

      if($test>0){
        return true;
      }

      $query="
        INSERT INTO ".$prefix."db_lines(
        event_id,
        period_id,
        sportsbook_id,
        timestamp,
        away_spread_point,
        home_spread_point,
        away_spread_price,
        home_spread_price,
        total_point,
        total_over_price,
        total_under_price,
        away_money,
        home_money,
        draw_money,
        line_type
        ) values (
        {$lineArray['event_id']},
        {$lineArray['period_id']},
        {$lineArray['sportsbook']},
        '{$lineArray['time']}',
        {$lineArray['ps']['away_spread']},
        {$lineArray['ps']['home_spread']},
        {$lineArray['ps']['away_price']},
        {$lineArray['ps']['home_price']},
        {$lineArray['total']['total']},
        {$lineArray['total']['over_price']},
        {$lineArray['total']['under_price']},
        {$lineArray['money']['away_money']},
        {$lineArray['money']['home_money']},
        {$lineArray['money']['draw_money']},
        '{$lineArray['type']}'
        );";
        return $this->_query($query);

    }


    public function addScore($scoreArray){
      global $wpdb;
      $prefix = $wpdb->prefix;
      $time = $this->convertTime($scoreArray['time']);

      ##look for the same score this can be accomplished with database restrictions or other methods..
      $selectquery="select * from ".$prefix."db_score where timestamp = '{$time}' and period_id={$scoreArray['period_id']} and event_id = {$scoreArray['event_id']};";


      $test = $this->_query($selectquery);

      if(count($test)>0){
        return true;
      }

      $query="
        INSERT INTO ".$prefix."db_score (
        event_id,
        league_id,
        away_rotation_id,
        home_rotation_id,
        home_score,
        away_score,
        description,
        period_id,
        period_name,
        timestamp,
        final_flag
        ) values (
        {$scoreArray['event_id']},
        {$scoreArray['league_id']},
        {$scoreArray['away_rot']},
        {$scoreArray['home_rot']},
        {$scoreArray['home_score']},
        {$scoreArray['away_score']},
        '{$scoreArray['description']}',
        {$scoreArray['period_id']},
        '{$scoreArray['period']}',
        '{$time}',
        '{$scoreArray['final']}'
        );";

        return $this->_query($query);
    }


    public function addSportsbook($sportsbookArray){
       global $wpdb;
      $prefix = $wpdb->prefix;
      $query="INSERT INTO ".$prefix."db_sportsbook (
        sportsbook_id,
        abbreviation,
        name
        ) values (
        {$sportsbookArray['id']},
        '{$sportsbookArray['name']}',
        '{$sportsbookArray['abbreviation']}'
        );";
        return $this->_query($query);
    }

    public function addLeague($leagueArray){
       global $wpdb;
      $prefix = $wpdb->prefix;
      $query="INSERT INTO ".$prefix."db_leagues (
        league_id,
        abbreviation,
        name,
        sport_id,
        sport_name,
        sport_abbreviation
        ) values (
        {$leagueArray['id']},
        '{$leagueArray['name']}',
        '{$leagueArray['abbreviation']}',
        {$leagueArray['sport_id']},
        '{$leagueArray['sport_name']}',
        '{$leagueArray['sport_abbreviation']}'
        );";

        return $this->_query($query);
    }


    public function addTeam($teamsArray){
       global $wpdb;
      $prefix = $wpdb->prefix;
      $query="INSERT INTO ".$prefix."db_team (
        team_id,
        abbreviation,
        name,
        full_name,
        league_id
        ) values (
        {$teamsArray['id']},
        '{$teamsArray['abbreviation']}',
        '{$teamsArray['name']}',
        '{$teamsArray['full_name']}',
        {$teamsArray['league_id']}
        );";

        return $this->_query($query);  

    }

    ## date / time change
    private function updateEventDate($event_id,$event_date){

        $query="UPDATE ".$prefix."db_eventS set start_time = '{$event_date}' and time_changed = 1 where event_id = $event_id;";
        return $this->_query($query);
    }


    ## start event
    private function updateState($event_id,$state_str){
      $query="UPDATE ".$prefix."db_eventS set state = '$state_str' where event_id=$event_id;";
      return $this->_query($query);
    }


    ##update eventstate
    public function updateEventState($eventArray){
      
      $event_id = $eventArray['event_id'];

      switch($eventArray['state_id']){ 

        case 1:  // state changes like pending, started, halftime, final
        case 7:
        case 13:
        case 10:
          $this->updateState($event_id, $eventArray['name']);
        break;
      
        case 3:
        case 4:
          $new_date = $eventArray['current_date'];
          $this->updateEventDate($event_id,$new_date);
        break;
      
      }

    }


    #### display:

    public function getEvents($league_id=null){
       global $wpdb;
      $prefix = $wpdb->prefix;
      if($league_id!=null){
        $filter = " where league_id={$league_id} ";
      }
      $sql = "SELECT * from ".$prefix."db_events {$filter};";
      
      return $this->_query($sql);  
    }

    public function convertTime($time,$timezoneString='America/Tortola',$type='datetime'){
      $baseTimezone = new DateTimeZone('UTC');
      $dateTime =  new DateTime($time, $baseTimezone);
      
      $userTimezone = new DateTimeZone($timezoneString);
      $pst = $dateTime->setTimezone($userTimezone);
      if($type == 'datetime'){
        return $pst->format('Y-m-d h:i:s');
      }elseif($type=='date'){
        return $pst->format('Y-m-d');
      }else{
         return $pst->format('g:i A');
      }
    }

    /* Populate Event */

    public function checkGameType($group_title){
      ## group types array:
      $event_types = array(1=>'FUTURES',2=>'IN-GAME');
      foreach($event_types as $key=>$event_type){
        if(strpos($group_title, $event_type)){
          return $event_type;
        }
      }
        ## regular game
        return 'REGULAR';
    }

    public function db_league_live_odds_function($atts){
      global $wpdb;
      $a = shortcode_atts( array(
              'league' => 'NFL'
          ), $atts );

      $requested_uri        =  $_SERVER['REQUEST_URI'];
      $requested_uri_array  =  explode("/",$requested_uri);
      $first_category       =  $requested_uri_array[1]; 
      $second_category      =  $requested_uri_array[2]; 
      $third_category       =  $requested_uri_array[3]; 

      ob_start();
      ?>
      <link href="//cdn.datatables.net/1.10.13/css/jquery.dataTables.min.css">
      <script src="//cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js" type="text/javascript"></script>
      <script type="text/javascript">
      </script>
      <?php
      if(!empty($third_category)){
        $t = strtotime($third_category);
        $d = (date("Y-m-d 4:i:s A",$t));
      }else{
        $t=time()-14400;
        $d = (date("Y-m-d h:i:s A",$t));
      }
      $datetime = $this->convertTime($d,'America/Tortola','datetime');
      $datetime =  date('l, F jS',strtotime($datetime));
      $league_id = $wpdb->get_var("SELECT league_id FROM ".$wpdb->prefix."db_leagues WHERE name='".$a['league']."'");
      $db_events_query = "SELECT ev.* FROM ".$wpdb->prefix."db_events ev  WHERE ev.league_id=".$league_id." AND ev.grouping_name LIKE '%".$datetime."%' AND ev.home_team_id!=0 AND ev.away_team_id!=0 AND ev.type='TEAM_EVENT' AND ev.sub_type='REGULAR'  order by ev.away_team_rotation ASC,ev.home_team_rotation ASC;";
      $results = $wpdb->get_results($db_events_query);
      $prev_time = strtotime('-1 day', strtotime($datetime));
      $next_time = strtotime('+1 day',strtotime($datetime));
      $prev_sub_link = '/live-odds/'.$second_category.'/'.date('Ymd',$prev_time);
      $next_sub_link = '/live-odds/'.$second_category.'/'.date('Ymd',$next_time);
      $prev_link     =  home_url($prev_sub_link);
      $next_link     =  home_url($next_sub_link);
      $class = $a['league']."-live-odds";
      ?>
      <div class="nextprev" style="float:right;"><a style="margin-right:10px;" href="<?php echo $prev_link; ?>">Previous</a>|<p style="display:inline; margin: 0px 10px;"><?php echo date('D, M j, Y',strtotime($datetime)) ?></p>|<a style="margin-left:10px;" href="<?php echo $next_link; ?>">Next</a></div>
      <?php
      if(count($results)>0){
        echo "<p class='grouping_name'><b>".$results[0]->grouping_name."</b></p>";
        ?>
        <table id="live_score" class="display nowrap dataTable dtr-inline <?php echo $class; ?> tableOdds" cellspacing="0" width="100%" role="grid" aria-describedby="example_info" style="width: 100%;">
          <thead>
              <tr>
                  <th>Rot</th>
                  <th>Opener</th>
                  <th>Team</th>
                  <th>Time</th>
                 <th>Score</th>
                  <th>Westgate</th>
                  <th>SIA</th>
                  <th>5Dimes</th>
            </tr>
          </thead>
          <tfoot>
              <tr>
                  <th>Rot</th>
                  <th>Opener</th>
                  <th>Team</th>
                  <th>Time</th>
                 <th>Score</th>
                  <th>Westgate</th>
                  <th>SIA</th>
                  <th>5Dimes</th>
            </tr>
          </tfoot>
          <tbody>
            <?php
            foreach ($results as $key => $result) {

                $event_id           =   $result->event_id;
                $league_id          =   $result->league_id;
                $start_time         =   $result->start_time;
                $away_rotation      =   $result->away_team_rotation;
                $home_rotation      =   $result->home_team_rotation;
                $away_team          =   $result->away_team_name;
                $home_team          =   $result->home_team_name;
                $grouping_name      =   $result->grouping_name;
                $score_result       = '';
                $status_result      = '';
                

                $status_query       =   "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." order by final_flag DESC,period_id DESC,timestamp DESC limit 1";
                $status_result      =   $wpdb->get_row($status_query);
                if($status_result->final_flag ==1){
                  $score_query        =   "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_id=0";
                  $score_result       =   $wpdb->get_row($score_query);
                  $fina_class         =  1;
                }else{
                  if($a['league'] == 'MLB'){
                      $score_query        =   "SELECT sum(home_score) as home_score,sum(away_score) as away_score  FROM  ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_id!=0";
                      $score_result       =   $wpdb->get_row($score_query);
                  }else{
                      $score_query        =   "SELECT sum(home_score) as home_score,sum(away_score) as away_score  FROM ( SELECT max(timestamp),home_score,away_score,period_name  FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_id!=0 group by period_name) as temp";
                      $score_result       =   $wpdb->get_row($score_query);
                  }
                
                }
                $open_query         =   "SELECT * FROM ".$wpdb->prefix."db_lines WHERE event_id=".$event_id." AND sportsbook_id IN(92,119,139) AND line_type='open' AND period_id=1  ORDER by timestamp DESC LIMIT 1";
                $open_result        =   $wpdb->get_row($open_query);


                $westgate_query     =   "SELECT * FROM ".$wpdb->prefix."db_lines WHERE event_id=".$event_id." AND sportsbook_id=119 AND line_type='current' AND period_id=1 ORDER by timestamp DESC LIMIT 1";
                $westgate_result    =    $wpdb->get_row($westgate_query);

                $sia_query          =   "SELECT * FROM ".$wpdb->prefix."db_lines WHERE event_id=".$event_id." AND sportsbook_id=139 AND line_type='current' AND period_id=1 ORDER by  timestamp DESC LIMIT 1";
                $sia_result         =    $wpdb->get_row($sia_query);

                $fivedimes_query    =   "SELECT * FROM ".$wpdb->prefix."db_lines WHERE event_id=".$event_id." AND sportsbook_id=92 AND line_type='current' AND period_id=1 ORDER by timestamp DESC LIMIT 1";
                $fivedimes_result   =    $wpdb->get_row($fivedimes_query);
                if($grouping_name != $previous_group && $key>1){
                  ?>
                  </tbody>
                  </table>
                  <p class="group_name"><b><?php echo $grouping_name; ?></b></p>
                  <table id="live_score" class="display nowrap dataTable dtr-inline <?php echo $class; ?> tableOdds" cellspacing="0" width="100%" role="grid" aria-describedby="example_info" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Rot</th>
                            <th>Opener</th>
                            <th>Team</th>
                            <th>Time</th>
                           <th>Score</th>
                            <th>Westgate</th>
                            <th>SIA</th>
                            <th>5Dimes</th>
                      </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>Rot</th>
                            <th>Opener</th>
                            <th>Team</th>
                            <th>Time</th>
                           <th>Score</th>
                            <th>Westgate</th>
                            <th>SIA</th>
                            <th>5Dimes</th>
                      </tr>
                    </tfoot>
                    <tbody>
                    <?php
                }

                ?>
                <tr>
                    <td>
                        <?php echo sprintf("%03d", $away_rotation); ?><br>
                        <?php echo sprintf("%03d", $home_rotation);  ?>

                    </td>
                    <td class="alignRight oddsOpener" >
                      <?php 
                          if($a['league'] == 'NBA' || $a['league'] == 'NFL'){
                              if(empty($open_result)){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                              }else{
                                 echo $open_result->away_spread_point>0?"+".number_format($open_result->away_spread_point,1):number_format($open_result->away_spread_point,1);
                                 echo "<br><div class='oddsAlignMiddleOne'>".$open_result->total_point."</div>";
                                 echo $open_result->home_spread_point>0?"+".number_format($open_result->home_spread_point,1):number_format($open_result->home_spread_point,1);
                                
                              }
                          }elseif($a['league'] == 'MLB' || $a['league'] == 'NHL'){
                              if(empty($open_result)){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                              }else{
                                if($a['league'] == 'MLB'){
                                  echo $open_result->away_money>0?"+".number_format($open_result->away_money):number_format($open_result->away_money);
                                  echo "<br><div class='oddsAlignMiddleOne'>".$open_result->total_point."</div>";
                                  echo $open_result->home_mone>0?"+".number_format($open_result->home_money):number_format($open_result->home_money);
                                  
                              }else{
                                  echo  $open_result->away_money>0?"+".number_format($open_result->away_money):number_format($open_result->away_money);
                                  echo "<br><div class='oddsAlignMiddleOne'>".$open_result->total_point."</div>";
                                  echo $open_result->home_mone>0?"+".number_format($open_result->home_money):number_format($open_result->home_money);
                                 
                              }
                               
                              }

                          }
                          
                              
                         ?>
                    </td>
                    <td>
                        <?php echo $away_team;  ?><span class="pitcher_name"><?php echo $result->away_pitcher; ?><?php if($a['league']=='MLB' && $result->away_pitcher_hand!='' )   echo $result->away_pitcher_hand=='RIGHT'?'-R':'-L'; ?></span><br>
                        <?php echo $home_team;  ?><span class="pitcher_name"><?php echo $result->home_pitcher; ?><?php if($a['league']=='MLB' && $result->home_pitcher_hand!='')   echo $result->home_pitcher_hand=='RIGHT'?'-R':'-L'; ?></span>
                    </td>
                    <td>
                        <?php 
                            echo $this->convertTime($start_time,'America/Tortola','time');
                          ?>
                    </td>
                    <td>
                        <?php 
                          if($score_result->home_score != '' || $score_result->away_score){
                                echo $fina_class==1?'<span class="final_score">':'';
                                echo $score_result->away_score;
                                echo "<br>";
                               echo $score_result->home_score;
                                echo "<span class='score_period_name'>";
                                echo $status_result->period_name;
                                 echo "</span>";
                                echo $fina_class==1?'</span>':'';

                          }else{
                            echo "-";
                          }
                             
                         ?>
                    </td>
                    <td  class="alignRight oddsWestgate">
                    <?php 
                      if($a['league'] == 'NBA' || $a['league'] == 'NFL'){
                          if(empty($westgate_result)){
                              echo "-";
                              echo "<br>";
                              echo "-";
                          }else{
                             echo $westgate_result->away_spread_point>0?"+".number_format($westgate_result->away_spread_point,1):number_format($westgate_result->away_spread_point,1);
                             echo "<br><div class='oddsAlignMiddleOne'>".$westgate_result->total_point."</div>";
                             echo $westgate_result->home_spread_point>0?"+".number_format($westgate_result->home_spread_point,1):number_format($westgate_result->home_spread_point,1);
                           
                          }
                      }elseif($a['league'] == 'MLB' || $a['league'] == 'NHL'){
                            if(empty($westgate_result)){
                                echo "-";
                                echo "<br>";
                                echo "-";
                            }else{
                              if($a['league'] == 'MLB'){
                                  echo $westgate_result->away_money>0?"+".number_format($westgate_result->away_money):number_format($westgate_result->away_money);
                                  echo "<br><div class='oddsAlignMiddleOne'>".$westgate_result->total_point."</div>";
                                  echo $westgate_result->home_mone>0?"+".number_format($westgate_result->home_money):number_format($westgate_result->home_money);   
                              }else{
                                  echo  $westgate_result->away_money>0?"+".number_format($westgate_result->away_money):number_format($westgate_result->away_money);
                                  echo "<br><div class='oddsAlignMiddleOne'>".$westgate_result->total_point."</div>";
                                  echo $westgate_result->home_mone>0?"+".number_format($westgate_result->home_money):number_format($westgate_result->home_money);
                                 
                              }
                            }

                        }
                              
                    ?>
                    </td>
                    <td class="alignRight oddsSia">
                      <?php
                         if($a['league'] == 'NBA' || $a['league'] == 'NFL'){ 
                          if(empty($sia_result)){
                              echo "-";
                              echo "<br>";
                              echo "-";
                          }else{
                            echo $sia_result->away_spread_point>0?"+".number_format($sia_result->away_spread_point,1):number_format($sia_result->away_spread_point,1);
                            echo "<br><div class='oddsAlignMiddleOne'>".$sia_result->total_point."</div>";
                            echo $sia_result->home_spread_point>0?"+".number_format($sia_result->home_spread_point,1):number_format($sia_result->home_spread_point,1);
                           
                          }
                        }elseif($a['league'] == 'MLB' || $a['league'] == 'NHL'){
                            if(empty($sia_result)){
                                echo "-";
                                echo "<br>";
                                echo "-";
                            }else{
                              if($a['league'] == 'MLB'){
                                  echo $sia_result->away_money>0?"+".number_format($sia_result->away_money):number_format($sia_result->away_money);
                                  echo "<br><div class='oddsAlignMiddleOne'>".$sia_result->total_point."</div>";
                                  echo $sia_result->home_mone>0?"+".number_format($sia_result->home_money):number_format($sia_result->home_money);
                                 
                                  
                              }else{
                                  echo  $sia_result->away_money>0?"+".number_format($sia_result->away_money):number_format($sia_result->away_money);
                                  echo "<br><div class='oddsAlignMiddleOne'>".$sia_result->total_point."</div>";
                                  echo $sia_result->home_mone>0?"+".number_format($sia_result->home_money):number_format($sia_result->home_money);
                                 
                              }
                              
                            }

                        }
                              
                         
                    ?>
                    </td>
                    <td class="alignRight oddsfivedimes">  
                         <?php
                         if($a['league'] == 'NBA' || $a['league'] == 'NFL'){  
                            if(empty($fivedimes_result)){
                              echo "-";
                              echo "<br>";
                              echo "-";
                            }else{
                              echo $fivedimes_result->away_spread_point>0?"+".number_format($fivedimes_result->away_spread_point,1):number_format($fivedimes_result->away_spread_point,1);
                              echo "<br><div class='oddsAlignMiddleOne'>".$fivedimes_result->total_point."</div>";
                              echo $fivedimes_result->home_spread_point>0?"+".number_format($fivedimes_result->home_spread_point,1):number_format($fivedimes_result->home_spread_point,1);
                              
                            }
                          }elseif($a['league'] == 'MLB' || $a['league'] == 'NHL'){
                            if(empty($fivedimes_result)){
                                echo "-";
                                echo "<br>";
                                echo "-";
                            }else{
                               if($a['league'] == 'MLB'){
                                  echo $fivedimes_result->away_money>0?"+".number_format($fivedimes_result->away_money):number_format($fivedimes_result->away_money);
                                  echo "<br><div class='oddsAlignMiddleOne'>".$fivedimes_result->total_point."</div>";
                                  echo $fivedimes_result->home_mone>0?"+".number_format($fivedimes_result->home_money):number_format($fivedimes_result->home_money);
                                 
                                  
                              }else{
                                  echo  $fivedimes_result->away_money>0?"+".number_format($fivedimes_result->away_money):number_format($fivedimes_result->away_money);
                                  echo "<br><div class='oddsAlignMiddleOne'>".$fivedimes_result->total_point."</div>";
                                  echo $fivedimes_result->home_mone>0?"+".number_format($fivedimes_result->home_money):number_format($fivedimes_result->home_money);
                                 
                              }
                             
                            }

                          }
                              
                         ?>
                    </td>
                </tr>
                <?php

                $previous_group = $grouping_name;

            }
            ?>
          </tbody>
        </table>
        <?php
      }else{
        echo "<p>No Records found.</p>";
      }
      $content = ob_get_contents();
      ob_end_clean();
      
      return $content;
      
    }

    public function db_league_live_score_function($atts){
      global $wpdb;
      $a = shortcode_atts( array(
              'league' => 'NFL'
          ), $atts );
      $requested_uri        =  $_SERVER['REQUEST_URI'];
      $requested_uri_array  =  explode("/",$requested_uri);
      $first_category       =  $requested_uri_array[1]; 
      $second_category      =  $requested_uri_array[2]; 
      $third_category       =  $requested_uri_array[3]; 
      ob_start();
      ?>
      <link href="//cdn.datatables.net/1.10.13/css/jquery.dataTables.min.css">
      <script src="//cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js" type="text/javascript"></script>
      <script type="text/javascript">
        $(document).ready(function(){
          $('#live_score').DataTable();
      });
      </script>
      <?php
        if(!empty($third_category)){
          $t = strtotime($third_category);
          $d = (date("Y-m-d 4:i:s A",$t));
        }else{
          $t=time()-14400;
          $d = (date("Y-m-d h:i:s A",$t));
        }
        $datetime = $this->convertTime($d,'America/Tortola','datetime');
        $datetime =  date('l, F jS',strtotime($datetime));
        $league_id = $wpdb->get_var("SELECT league_id FROM ".$wpdb->prefix."db_leagues WHERE name='".$a['league']."'");
        $db_events_query = "SELECT ev.* FROM ".$wpdb->prefix."db_events ev  WHERE ev.league_id=".$league_id." AND ev.grouping_name LIKE '%".$datetime."%' AND ev.type='TEAM_EVENT' AND ev.sub_type='REGULAR'  order by ev.away_team_rotation ASC,ev.home_team_rotation ASC;";
        $results = $wpdb->get_results($db_events_query);
        $prev_time = strtotime('-1 day', strtotime($datetime));
        $next_time = strtotime('+1 day', strtotime($datetime));
        $prev_sub_link = '/scores-and-info/'.$second_category.'/'.date('Ymd',$prev_time);
        $next_sub_link = '/scores-and-info/'.$second_category.'/'.date('Ymd',$next_time);
        $prev_link     =  home_url($prev_sub_link);
        $next_link     =  home_url($next_sub_link);
       $class = $a['league']."-live-scores";
      ?>
      <div class="nextprev" style="float:right;"><a style="margin-right:10px;" href="<?php echo $prev_link; ?>">Previous</a>|<p style="display:inline; margin: 0px 10px;"><?php echo date('D, M j, Y',strtotime($datetime)) ?></p>|<a style="margin-left:10px;" href="<?php echo $next_link; ?>">Next</a></div>
      <?php
      if(count($results)>0){
         echo "<p class='grouping_name'><b>".$results[0]->grouping_name."</b></p>";
        ?>
        <table id="live_score" class="display nowrap dataTable dtr-inline <?php echo $class; ?> tableScores" cellspacing="0" width="100%" role="grid" aria-describedby="example_info" style="width: 100%;">
            <?php

        if($a['league'] == 'NBA' || $a['league'] == 'NFL'){

            ?>
            <thead>
              <tr>
                  <th>Team</th>
                  <th>Time</th>
                  <th>1st</th>
                  <th>2nd</th>
                  <th>3rd</th>
                  <th>4th</th>
                  <th>Final</th>
                  <th>Status</th>
                  <th>Current Line</th>
                  <th>1st H Total</th>
                  <th>2nd H Total</th>
            </tr>
          </thead>
          <tfoot>
              <tr>
                  <th>Team</th>
                  <th>Time</th>
                  <th>1st</th>
                  <th>2nd</th>
                  <th>3rd</th>
                  <th>4th</th>
                  <th>Final</th>
                  <th>Status</th>
                  <th>Current Line</th>
                  <th>1st H Total</th>
                  <th>2nd H Total</th> 
            </tr>
          </tfoot>
          <tbody>
            <?php
              foreach ($results as $key => $result) {

                $event_id           =   $result->event_id;
                $league_id          =   $result->league_id;
                $start_time         =   $result->start_time;
                $away_rotation      =   $result->away_team_rotation;
                $home_rotation      =   $result->home_team_rotation;
                $away_team          =   $result->away_team_name;
                $home_team          =   $result->home_team_name;
                $grouping_name      =   $result->grouping_name;
               /* echo $event_id." <br>  ";*/
               if($grouping_name != $previous_group && $key>1){
                  ?>
                  </tbody>
                  </table>
                  <p class="group_name"><b><?php echo $grouping_name; ?></b></p>
                  <table id="live_score" class="display nowrap dataTable dtr-inline <?php echo $class; ?> tableScores" cellspacing="0" width="100%" role="grid" aria-describedby="example_info" style="width: 100%;">
                    <thead>
                      <tr>
                          <th>Team</th>
                          <th>Time</th>
                          <th>1st</th>
                          <th>2nd</th>
                          <th>3rd</th>
                          <th>4th</th>
                          <th>Final</th>
                          <th>Status</th>
                          <th>Current Line</th>
                          <th>1st H Total</th>
                          <th>2nd H Total</th>
                    </tr>
                  </thead>
                  <tfoot>
                      <tr>
                          <th>Team</th>
                          <th>Time</th>
                          <th>1st</th>
                          <th>2nd</th>
                          <th>3rd</th>
                          <th>4th</th>
                          <th>Final</th>
                          <th>Status</th>
                          <th>Current Line</th>
                          <th>1st H Total</th>
                          <th>2nd H Total</th>
                    </tr>
                  </tfoot>
                  <tbody>
              <?php
                }

                ?>
                <tr>
                    <td>
                        <?php echo $away_team; ?><br>
                        <?php echo $home_team;  ?>

                    </td>
                    <td>
                      <?php 
                               echo $this->convertTime($start_time,'America/Tortola','time');
                          ?>
                    </td>
                    <td>
                        <?php 
                              $first_query = "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='1st Q' order by timestamp DESC limit 1";
                              $first_result = $wpdb->get_row($first_query);
                               if(empty($first_result)){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  echo $first_result->home_score;
                                  echo "<br>";
                                  echo $first_result->away_score;
                                }
                         ?>
                    </td>
                    <td>
                        <?php 
                              $second_query = "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='2nd Q' order by timestamp DESC limit 1";
                              $second_result = $wpdb->get_row($second_query);
                               if(empty($second_result)){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  echo $second_result->home_score;
                                   echo "<br>";
                                  echo $second_result->away_score;
                                 
                                }
                         ?>
                    </td>
                    <td>
                        <?php 
                              $third_query = "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='3rd Q' order by timestamp DESC limit 1";
                              $third_result = $wpdb->get_row($third_query);
                               if(empty($third_result)){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  echo $third_result->home_score;
                                   echo "<br>";
                                  echo $third_result->away_score;
                                 
                                }
                         ?>
                    </td>
                    <td>
                        <?php 
                              $forth_query = "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='4th Q' order by timestamp DESC limit 1";
                              $forth_result = $wpdb->get_row($forth_query);
                               if(empty($forth_result)){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  echo $forth_result->home_score;
                                  echo "<br>";
                                  echo $forth_result->away_score;

                                }
                         ?>
                    </td>
                    <td>
                         <?php 
                              $final_query = "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='FINAL' order by timestamp DESC limit 1";
                              $final_result = $wpdb->get_row($final_query);
                               if(empty($final_result)){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  echo $final_result->away_score;
                                  echo "<br>";
                                  echo $final_result->home_score;
                                }
                         ?>
                         
                    </td>
                    <td>  
                          <?php 
                              $status_query = "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." order by final_flag DESC,period_id DESC,timestamp DESC limit 1";
                              $status_result = $wpdb->get_row($status_query);
                               if(empty($status_result)){
                                  echo "-";
                                }else{
                                  echo $status_result->period_name;
                                }
                          ?>

                    </td>
                    <td class="alignRight nba-currentline">  
                        <?php 
                            $current_query     =   "SELECT * FROM ".$wpdb->prefix."db_lines WHERE event_id=".$event_id." AND sportsbook_id IN(92,119,139) AND line_type='current' AND period_id=1 ORDER by timestamp DESC LIMIT 1";
                            $current_result    =    $wpdb->get_row($current_query);

                            if(empty($current_result)){
                                echo "-";
                                echo "<br>";
                                echo "-";
                            }else{
                              echo $current_result->away_spread_point>0?"+".number_format($current_result->away_spread_point,1):number_format($current_result->away_spread_point,1);
                              echo "<br><div class='oddsAlignMiddleOne'>".$current_result->total_point."</div>";
                              echo $current_result->home_spread_point>0?"+".number_format($current_result->home_spread_point,1):number_format($current_result->home_spread_point,1);
                             
                            }
                         ?>
                    </td>
                    <td>  
                        <?php 
                                if(!empty($first_result) && !empty($second_result)){
                                   $firsth_home_score = $first_result->home_score+$second_result->home_score;
                                   $firsth_away_score = $first_result->away_score+$second_result->away_score;
                                   if($firsth_home_score !== ''){
                                      echo $firsth_home_score;
                                      echo "<br>";
                                    }else{
                                       echo "-";
                                      echo "<br>";
                                    }
                                    if($firsth_away_score !== ''){
                                      echo $firsth_away_score;
                                      echo "<br>";
                                    }else{
                                       echo "-";
                                      echo "<br>";
                                    }
                                }else{
                                    echo "-";
                                    echo "<br>";
                                    echo "-";
                                }
                               
                         ?>
                    </td>
                    <td>  
                        <?php 
                                if(!empty($third_result) && !empty($forth_result)){
                                 $secondh_home_score = $third_result->home_score+$forth_result->home_score;
                                 $secondh_away_score = $third_result->away_score+$forth_result->away_score;
                                 if($secondh_home_score != ''){
                                    echo $secondh_home_score;
                                    echo "<br>";
                                  }else{
                                     echo "-";
                                    echo "<br>";
                                  }
                                  if($secondh_away_score != ''){
                                    echo $secondh_away_score;
                                    echo "<br>";
                                  }else{
                                     echo "-";
                                    echo "<br>";
                                  }
                                }else{
                                    echo "-";
                                    echo "<br>";
                                    echo "-";
                                }

                         ?>
                    </td>
                </tr>
                <?php
                $previous_group = $grouping_name;
            }
        }elseif($a['league'] == 'MLB'){
            ?>
            <thead>
              <tr>
                  <th>Team</th>
                  <th>Time</th>
                  <th>Pitcher</th>
                  <th>1st</th>
                  <th>2nd</th>
                  <th>3rd</th>
                  <th>4th</th>
                  <th>5th</th>
                  <th>6th</th>
                  <th>7th</th>
                  <th>8th</th>
                  <th>9th</th>
                  <th>R</th>
                  <th>Status</th>
                  <th>Current Line</th>
                  <th>First 5 Score</th>     
            </tr>
          </thead>
          <tfoot>
              <tr>
                  <th>Team</th>
                  <th>Time</th>
                  <th>Pitcher</th>
                  <th>1st</th>
                  <th>2nd</th>
                  <th>3rd</th>
                  <th>4th</th>
                  <th>5th</th>
                  <th>6th</th>
                  <th>7th</th>
                  <th>8th</th>
                  <th>9th</th>
                  <th>R</th>
                  <th>Status</th>
                  <th>Current Line</th>
                  <th>First 5 Score</th>  
            </tr>
          </tfoot>
          <tbody>
            <?php
              foreach ($results as $key => $result) {

                $event_id           =   $result->event_id;
                $league_id          =   $result->league_id;
                $start_time         =   $result->start_time;
                $away_rotation      =   $result->away_team_rotation;
                $home_rotation      =   $result->home_team_rotation;
                $away_team          =   $result->away_team_name;
                $home_team          =   $result->home_team_name;
                $grouping_name      =   $result->grouping_name;
                //echo $event_id."<br>";
                $first_home_score     = '';
                $first_away_score     = '';
                $second_home_score    = '';
                $second_away_score    = '';
                $third_home_score     = '';
                $third_away_score     = '';
                $forth_home_score     = '';
                $forth_away_score     = '';
                $fifth_home_score     = '';
                $fifth_away_score     = '';
                $sixth_home_score     = '';
                $sixth_away_score     = '';
                $seventh_home_score   = '';
                $seventh_away_score   = '';
                $eighth_home_score    = '';
                $eighth_away_score    = '';
                $ninth_home_score     = '';
                $ninth_away_score     = '';
                $r_home_score         = '';
                $r_away_score         = '';
                if($grouping_name != $previous_group && $key>1){
                  ?>
                  </tbody>
                  </table>
                  <p class="group_name"><b><?php echo $grouping_name; ?></b></p>
                  <table id="live_score" class="display nowrap dataTable dtr-inline <?php echo $class; ?> tableScores" cellspacing="0" width="100%" role="grid" aria-describedby="example_info" style="width: 100%;">
                  <thead>
                    <tr>
                        <th>Team</th>
                        <th>Time</th>
                        <th>Pitcher</th>
                        <th>1st</th>
                        <th>2nd</th>
                        <th>3rd</th>
                        <th>4th</th>
                        <th>5th</th>
                        <th>6th</th>
                        <th>7th</th>
                        <th>8th</th>
                        <th>9th</th>
                        <th>R</th>
                        <th>Status</th>
                        <th>Current Line</th>
                        <th>First 5 Score</th>     
                  </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>Team</th>
                        <th>Time</th>
                        <th>Pitcher</th>
                        <th>1st</th>
                        <th>2nd</th>
                        <th>3rd</th>
                        <th>4th</th>
                        <th>5th</th>
                        <th>6th</th>
                        <th>7th</th>
                        <th>8th</th>
                        <th>9th</th>
                        <th>R</th>
                        <th>Status</th>
                        <th>Current Line</th>
                        <th>First 5 Score</th> 
                  </tr>
                </tfoot>
                <tbody>
              <?php
                }
                ?>
                <tr>
                    <td>
                        <?php echo $away_team; ?><br>
                        <?php echo $home_team;  ?>

                    </td>
                    <td>
                      <?php 
                               echo $this->convertTime($start_time,'America/Tortola','time');
                          ?>
                    </td>
                    <td>
                        
                        <?php if(!empty($result->away_pitcher)) { ?><span class="pitcher_name"><?php echo $result->away_pitcher; ?><?php   echo $result->away_pitcher_hand=='RIGHT'?'-R':'-L'; ?></span><?php }else{ echo "-"; } ?><br>
                        <?php if(!empty($result->home_pitcher)) { ?><span class="pitcher_name"><?php echo $result->home_pitcher; ?><?php   echo $result->home_pitcher_hand=='RIGHT'?'-R':'-L'; ?></span><?php }else{ echo "-"; } ?>
                         
                    </td>
                    <td>
                        <?php 
                              $firstt_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='TOP 1st' order by timestamp DESC limit 1";
                              $firstb_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='BOT 1st' order by timestamp DESC limit 1";
                              $firstt_result = $wpdb->get_row($firstt_query);
                              $firstb_result = $wpdb->get_row($firstb_query);
                               if(empty($firstt_result) && empty($firstb_result) ){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  $first_away_score = $firstt_result->away_score+$firstb_result->away_score;
                                  $first_home_score = $firstt_result->home_score+$firstb_result->home_score;
                                  echo $first_home_score;
                                   echo "<br>";
                                  echo $first_away_score;
                                 
                                  
                              }
                         ?>
                    </td>
                    <td>
                        <?php 
                              $secondt_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='TOP 2nd' order by timestamp DESC limit 1";
                              $secondb_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='BOT 2nd' order by timestamp DESC limit 1";
                              $secondt_result = $wpdb->get_row($secondt_query);
                              $secondb_result = $wpdb->get_row($secondb_query);
                               if(empty($secondt_result) && empty($secondb_result)) {
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  $second_away_score = $secondt_result->away_score+$secondb_result->away_score;
                                  $second_home_score = $secondt_result->home_score+$secondb_result->home_score;
                                  echo $second_home_score;
                                   echo "<br>";
                                  echo $second_away_score;
                                 
                                  
                              }
                         ?>
                    </td>
                    <td>
                        <?php 
                              $thirdt_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='TOP 3rd' order by timestamp DESC limit 1";
                              $thirdb_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='BOT 3rd' order by timestamp DESC limit 1";
                              $thirdt_result = $wpdb->get_row($thirdt_query);
                              $thirdb_result = $wpdb->get_row($thirdb_query);
                               if(empty($thirdt_result) && empty($thirdb_result) ){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  $third_away_score = $thirdt_result->away_score+$thirdb_result->away_score;
                                  $third_home_score = $thirdt_result->home_score+$thirdb_result->home_score;
                                  echo $third_home_score;
                                   echo "<br>";
                                  echo $third_away_score;
                                 
                                  
                              }
                         ?>
                    </td>
                    <td>
                        <?php 
                              $fortht_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='TOP 4th' order by timestamp DESC limit 1";
                              $forthb_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='BOT 4th' order by timestamp DESC limit 1";
                              $fortht_result = $wpdb->get_row($fortht_query);
                              $forthb_result = $wpdb->get_row($forthb_query);
                               if(empty($fortht_result) && empty($forthb_result) ){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  $forth_away_score = $fortht_result->away_score+$forthb_result->away_score;
                                  $forth_home_score = $fortht_result->home_score+$forthb_result->home_score;
                                  echo $forth_home_score;
                                   echo "<br>";
                                  echo $forth_away_score;
                                 
                                  
                              }
                         ?>
                    </td>
                    <td>  
                        <?php 
                              $fiftht_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='TOP 5th' order by timestamp DESC limit 1";
                              $fifthb_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='BOT 5th' order by timestamp DESC limit 1";
                              $fiftht_result = $wpdb->get_row($fiftht_query);
                              $fifthb_result = $wpdb->get_row($fifthb_query);
                               if(empty($fiftht_result) && empty($fifthb_result) ){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  $fifth_away_score = $fiftht_result->away_score+$fifthb_result->away_score;
                                  $fifth_home_score = $fiftht_result->home_score+$fifthb_result->home_score;
                                  echo $fifth_home_score;
                                   echo "<br>";
                                  echo $fifth_away_score;
                                 
                                  
                              }
                         ?>
                    </td>
                    <td>  
                        <?php 
                              $sixtht_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='TOP 6th' order by timestamp DESC limit 1";
                              $sixthb_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='BOT 6th' order by timestamp DESC limit 1";
                              $sixtht_result = $wpdb->get_row($sixtht_query);
                              $sixthb_result = $wpdb->get_row($sixthb_query);
                               if(empty($sixtht_result) && empty($sixthb_result) ){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  $sixth_away_score = $sixtht_result->away_score+$sixthb_result->away_score;
                                  $sixth_home_score = $sixtht_result->home_score+$sixthb_result->home_score;
                                  echo $sixth_home_score;
                                   echo "<br>";
                                  echo $sixth_away_score;
                                 
                                  
                              }
                         ?>
                    </td>
                    <td>  
                        <?php 
                              $seventht_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='TOP 7th' order by timestamp DESC limit 1";
                              $seventhb_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='BOT 7th' order by timestamp DESC limit 1";
                              $seventht_result = $wpdb->get_row($seventht_query);
                              $seventhb_result = $wpdb->get_row($seventhb_query);
                               if(empty($seventht_result) && empty($seventhb_result) ){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  $seventh_away_score = $seventht_result->away_score+$seventhb_result->away_score;
                                  $seventh_home_score = $seventht_result->home_score+$seventhb_result->home_score;
                                  echo $seventh_home_score;
                                   echo "<br>";
                                  echo $seventh_away_score;
                                 
                                  
                              }
                         ?>
                    </td>
                    <td>  
                        <?php 
                              $eightht_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='TOP 8th' order by timestamp DESC limit 1";
                              $eighthb_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='BOT 8th' order by timestamp DESC limit 1";
                              $eightht_result = $wpdb->get_row($eightht_query);
                              $eighthb_result = $wpdb->get_row($eighthb_query);
                               if(empty($eightht_result) && empty($eighthb_result) ){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  $eighth_away_score = $eightht_result->away_score+$eighthb_result->away_score;
                                  $eighth_home_score = $eightht_result->home_score+$eighthb_result->home_score;
                                  echo $eighth_home_score;
                                   echo "<br>";
                                  echo $eighth_away_score;
                                 
                                  
                              }
                         ?>
                    </td>
                    <td>  
                        <?php 
                              $nintht_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='TOP 9th' order by timestamp DESC limit 1";
                              $ninthb_query = "SELECT away_score,home_score FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='BOT 9th' order by timestamp DESC limit 1";
                              $nintht_result = $wpdb->get_row($nintht_query);
                              $ninthb_result = $wpdb->get_row($ninthb_query);
                               if(empty($nintht_result) && empty($ninthb_result) ){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  $ninth_away_score = $nintht_result->away_score+$ninthb_result->away_score;
                                  $ninth_home_score = $nintht_result->home_score+$ninthb_result->home_score;
                                  echo $ninth_home_score;
                                   echo "<br>";
                                  echo $ninth_away_score;
                                  
                              }
                         ?>
                    </td>
                    <td>  
                        <?php 
                              $status_query = "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." order by final_flag DESC,period_id DESC,timestamp DESC limit 1";
                              $status_result = $wpdb->get_row($status_query);
                              $r_away_score = $first_away_score+$second_away_score+$third_away_score+$forth_away_score+$fifth_away_score+$sixth_away_score+$seventh_away_score+$eighth_away_score+$ninth_away_score;
                             
                              $r_home_score = $first_home_score+$second_home_score+$third_home_score+$forth_home_score+$fifth_home_score+$sixth_home_score+$seventh_home_score+$eighth_home_score+$ninth_home_score;
                              
                              if($r_home_score==='' || empty($status_result)){
                                  echo "-";
                                    echo "<br>";
                               }else{
                                  echo $r_home_score;
                                  echo "<br>";
                               }

                              if($r_away_score==='' || empty($status_result) ){
                                    echo "-";
                                    echo "<br>";
                               }else{
                                  echo $r_away_score;
                                  echo "<br>";
                               }
                               
                         ?>
                    </td>
                    <td>  
                        <?php 
                              if(empty($status_result)){
                                  echo "-";
                                }else{
                                echo $status_result->period_name;
                              }
                         ?>
                    </td>
                    <td class="alignRight oddsfivedimes">  
                      <?php 
                        $current_query     =   "SELECT * FROM ".$wpdb->prefix."db_lines WHERE event_id=".$event_id." AND sportsbook_id IN(92,119,139) AND line_type='current'  AND period_id=1  ORDER by timestamp DESC LIMIT 1";
                        $current_result    =    $wpdb->get_row($current_query);

                          if(empty($current_result)){
                              echo "-";
                              echo "<br>";
                              echo "-";
                          }else{
                            echo $current_result->away_money>0?"+".number_format($current_result->away_money):number_format($current_result->away_money);
                            echo "<br><div class='oddsAlignMiddleOne'>".$current_result->total_point."</div>";
                            echo $current_result->home_mone>0?"+".number_format($current_result->home_money):number_format($current_result->home_money);
                                 
                                  
                              
                            
                          }
                         ?>
                        
                    </td>
                    <td>  
                         <?php 
                             
                              $five_away_score = $first_away_score+$second_away_score+$third_away_score+$forth_away_score+$fifth_away_score;
                             
                              $five_home_score = $first_home_score+$second_home_score+$third_home_score+$forth_home_score+$fifth_home_score;
                              
                               if($five_home_score==='' || empty($status_result) || $fifth_home_score===''){
                                  echo "-";
                                    echo "<br>";
                               }else{
                                  echo $five_home_score;
                                  echo "<br>";
                               }

                               if($five_away_score==='' || empty($status_result) || $fifth_away_score===''){
                                    echo "-";
                                    echo "<br>";
                               }else{
                                  echo $five_away_score;
                                  echo "<br>";
                               }
                         ?>
                    </td>
                </tr>
                <?php
                $previous_group = $grouping_name;
            }
        }elseif($a['league']=='NHL'){

            ?>
            <thead>
              <tr>
                  <th>Team</th>
                  <th>Time</th>
                  <th>1st</th>
                  <th>2nd</th>
                  <th>3rd</th>
                  <th>4th</th>
                  <th>Final</th>
                  <th>Status</th>
                  <th>Current Line</th>
                  <th>1st H Total</th>
                  <th>2nd H Total</th> 
            </tr>
          </thead>
          <tfoot>
              <tr>
                  <th>Team</th>
                  <th>Time</th>
                  <th>1st</th>
                  <th>2nd</th>
                  <th>3rd</th>
                  <th>4th</th>
                  <th>Final</th>
                  <th>Status</th>
                  <th>Current Line</th>
                  <th>1st H Total</th>
                  <th>2nd H Total</th>  
            </tr>
          </tfoot>
          <tbody>
            <?php
              foreach ($results as $key => $result) {

                $event_id           =   $result->event_id;
                $league_id          =   $result->league_id;
                $start_time         =   $result->start_time;
                $away_rotation      =   $result->away_team_rotation;
                $home_rotation      =   $result->home_team_rotation;
                $away_team          =   $result->away_team_name;
                $home_team          =   $result->home_team_name;
                $grouping_name      =   $result->grouping_name;
                //echo $event_id."<br>";
                 if($grouping_name != $previous_group && $key>1){
                  ?>
                  </tbody>
                  </table>
                  <p class="group_name"><b><?php echo $grouping_name; ?></b></p>
                  <table id="live_score" class="display nowrap dataTable dtr-inline <?php echo $class; ?> tableScores" cellspacing="0" width="100%" role="grid" aria-describedby="example_info" style="width: 100%;">
                    <thead>
                      <tr>
                          <th>Team</th>
                          <th>Time</th>
                          <th>1st</th>
                          <th>2nd</th>
                          <th>3rd</th>
                          <th>4th</th>
                          <th>Final</th>
                          <th>Status</th>
                          <th>Current Line</th>
                          <th>1st H Total</th>
                          <th>2nd H Total</th>
                    </tr>
                  </thead>
                  <tfoot>
                      <tr>
                          <th>Team</th>
                          <th>Time</th>
                          <th>1st</th>
                          <th>2nd</th>
                          <th>3rd</th>
                          <th>4th</th>
                          <th>Final</th>
                          <th>Status</th>
                          <th>Current Line</th>
                          <th>1st H Total</th>
                          <th>2nd H Total</th>
                    </tr>
                  </tfoot>
                  <tbody>
              <?php
                }

                ?>
                <tr>
                    <td>
                        <?php echo $away_team; ?><br>
                        <?php echo $home_team;  ?>

                    </td>
                    <td>
                      <?php 
                               echo $this->convertTime($start_time,'America/Tortola','time');
                          ?>
                    </td>
                    <td>
                        <?php 
                              $first_query = "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='1st P' order by timestamp DESC limit 1";
                              $first_result = $wpdb->get_row($first_query);
                              if(empty($first_result)){
                                echo "-";
                                echo "<br>";
                                echo "-";
                              }else{
                                echo $first_result->home_score;
                                 echo "<br>";
                                echo $first_result->away_score;
                               
                                
                              }
                         ?>
                    </td>
                    <td>
                        <?php 
                              $second_query = "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='2nd P' order by timestamp DESC limit 1";
                              $second_result = $wpdb->get_row($second_query);
                               if(empty($second_result)){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  echo $second_result->home_score;
                                  echo "<br>";
                                  echo $second_result->away_score;
                                  
                                 
                                }
                             ?>
                    </td>
                    <td>
                        <?php 
                              $third_query = "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='3rd P' order by timestamp DESC limit 1";
                              $third_result = $wpdb->get_row($third_query);
                               if(empty($third_result)){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  echo $third_result->home_score;
                                  echo "<br>";
                                  echo $third_result->away_score;
                                 
                                
                                }
                         ?>
                    </td>
                    <td>
                        <?php 
                             $forth_query   = "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND score_id>".$third_result->score_id." AND (period_name='4th P' ||  period_name='OT') order by timestamp DESC limit 1";
                             $forth_result  = $wpdb->get_row($forth_query);
                             if(empty($forth_result)){
                                echo "-";
                                echo "<br>";
                                echo "-";
                              }else{
                                echo $forth_result->home_score;
                                 echo "<br>";
                                echo $forth_result->away_score;
                               
                               
                              }
                         ?>
                    </td>
                    <td>
                         <?php 
                              $final_query = "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." AND period_name='FINAL' order by timestamp DESC limit 1";
                              $final_result = $wpdb->get_row($final_query);
                               if(empty($final_result)){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                                }else{
                                  echo $final_result->away_score;
                                  echo "<br>";
                                  echo $final_result->home_score;
                                }
                         ?>
                    </td>
                    <td>  
                        <?php 
                              $status_query = "SELECT * FROM ".$wpdb->prefix."db_score WHERE event_id=".$event_id." order by final_flag DESC,period_id DESC,timestamp DESC limit 1";
                              $status_result = $wpdb->get_row($status_query);
                               if(empty($status_result)){
                                  echo "-";
                                }else{
                                  echo $status_result->period_name;
                                }
                         ?>

                    </td>
                    <td class="alignRight oddsfivedimes">  
                        <?php 
                            $current_query     =   "SELECT * FROM ".$wpdb->prefix."db_lines WHERE event_id=".$event_id." AND sportsbook_id IN(92,119,139) AND line_type='current'  AND period_id=1  ORDER by timestamp DESC LIMIT 1";
                            $current_result    =    $wpdb->get_row($current_query);

                              if(empty($current_result)){
                                  echo "-";
                                  echo "<br>";
                                  echo "-";
                              }else{
                               
                                echo  $current_result->away_money>0?"+".number_format($current_result->away_money):number_format($current_result->away_money);
                                echo "<br><div class='oddsAlignMiddleOne'>".$current_result->total_point."</div>";
                                echo $current_result->home_mone>0?"+".number_format($current_result->home_money):number_format($current_result->home_money);
                               
                                
                            }
                         ?>
                    </td>
                    <td>  
                        <?php 
                             
                                if(!empty($first_result) && !empty($second_result)){
                                   $firsth_home_score = $first_result->home_score+$second_result->home_score;
                                   $firsth_away_score = $first_result->away_score+$second_result->away_score;
                                   if($firsth_home_score !== ''){
                                      echo $firsth_home_score;
                                      echo "<br>";
                                    }else{
                                       echo "-";
                                      echo "<br>";
                                    }
                                    if($firsth_away_score !== ''){
                                      echo $firsth_away_score;
                                      echo "<br>";
                                    }else{
                                       echo "-";
                                      echo "<br>";
                                    }
                                }else{
                                    echo "-";
                                    echo "<br>";
                                    echo "-";
                                }

                         ?>
                    </td>
                    <td>  
                        <?php 
                          
                            if(!empty($third_result) && !empty($forth_result)){
                             $secondh_home_score = $third_result->home_score+$forth_result->home_score;
                             $secondh_away_score = $third_result->away_score+$forth_result->away_score;
                             if($secondh_home_score !== ''){
                                echo $secondh_home_score;
                                echo "<br>";
                              }else{
                                 echo "-";
                                echo "<br>";
                              }
                              if($secondh_away_score !== ''){
                                echo $secondh_away_score;
                                echo "<br>";
                              }else{
                                 echo "-";
                                echo "<br>";
                              }
                            }else{
                                echo "-";
                                echo "<br>";
                                echo "-";
                            }
                         ?>
                    </td>
                </tr>
                <?php
                $previous_group = $grouping_name;
        }
        }else{

        }
        ?>
        </tbody>
        </table>
        <?php
        
      }else{
        echo "<p>No Records found.</p>";
      }
      $content = ob_get_contents();
      ob_end_clean();
      
      return $content;
      
    }
}

new xml_feed_importer();

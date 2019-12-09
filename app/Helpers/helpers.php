<?php 

if(!function_exists('addNews')){

  function messageNote($note){

    $news = array('user_id'=>'as','note'=>'ss','description'=>'sas','stories_id'=>'sas');
    //$feed = NewsFeed::create($note);

    // $ret = "";
    //   switch ($note) {
    //     case "1":
    //       $ret = "has created a story";
    //         break;
    //     case "2":
    //       $ret =  "has created a album";
    //         break;
    //     case "3":
    //       $ret =  "has deleted a story";
    //         break;
    //      case "4":
    //         $ret =  "has Updated a profile";
    //         break;
    //     default:
    //     $ret =  "Your favorite color is neither red, blue, nor green!";
    // }
    // return $ret;
  }


if(!function_exists('time_elapsed')){
    function time_elapsed($datetime, $full = false) {
      $now = new DateTime;
      $ago = new DateTime($datetime);
      $diff = $now->diff($ago);

      $diff->w = floor($diff->d / 7);
      $diff->d -= $diff->w * 7;

      $string = array(
          'y' => 'year',
          'm' => 'month',
          'w' => 'week',
          'd' => 'day',
          'h' => 'hour',
          'i' => 'minute',
          's' => 'second',
      );
      foreach ($string as $k => &$v) {
          if ($diff->$k) {
              $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
          } else {
              unset($string[$k]);
          }
      }

      if (!$full) $string = array_slice($string, 0, 1);
      return $string ? implode(', ', $string) . ' ago' : 'just now';
  }
 }
}
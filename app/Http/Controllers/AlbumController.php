<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Temporaryuploads;
use App\Albums;
use App\AlbumImages;
use App\NewsFeed;
use App\Stories;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;


class AlbumController extends Controller
{
    public function uploadMedia(Request $request)
    {
        if($request->hasFile('media')){
            $file = $request->file('media');
            $allowedfileExtension=['jpeg','jpg','png','mp4', 'avi', 'flv', 'wmv', 'mpg', 'mpeg', '3gp'];
            //Display File Name
            $filename = $file->getClientOriginalName();
            //Display File Extension
            $extension = $file->getClientOriginalExtension();
            //Display File Size
            // $size = $file->getSize();
            $file->getRealPath();
            if(!in_array(strtolower($extension), $allowedfileExtension))
            return response()->json([
                'status' => 0,
                'message' => config('constant.FILE_TYPE_NOT_ALLOWED')
            ]);
            //Display File Mime Type
            $filename = time().$filename;
            //Move Uploaded File
            $file->move(public_path('album_images'),$filename);
            $tempAdd = new Temporaryuploads;
            $tempAdd->user_id = $request->user->id;
            $tempAdd->file_path = $filename;
            $tempAdd->save();
            return response()->json([
                'status' => 1,
                'message' => config('constant.FILE_UPLOADED_SUCCESS')
            ]);
        }else{
            return response()->json([
                'status' => 0,
                'message' => config('constant.SELECT_FILE')
            ]);
        }
        
    } // uploadMedia Closing

    

    public function saveAlbum(Request $request)
    {   
         
        if(!$request->hasFile('media') || !isset($request->name) || !isset($request->place) || !isset($request->date) || !isset($request->stories_id))
            return response()->json([
                'status' => 0,
                'message' => config('constant.PARAMETER_MISSING')
            ]);

        $user_id = $request->user->id;
        $name = $request->name;
        $stories_id = $request->stories_id;
        $isAlbumAlreadyExist = Albums::where('name', '=', $name)->where('user_id', '=', $user_id)->first();
        
        $isStories = Stories::where('user_id', '=', $user_id)->where('id', '=', $stories_id)->count();

        if($isAlbumAlreadyExist)
        return response()->json([
            'status' => 1,
            'message' => config('constant.ALBUM_ALREADY_EXIST')
        ]);
     
        if(!$isStories)
        return response()->json([
            'status' => 1,
            'message' => "Stories Not added Please add Stories."
        ]);

        

        
        
        $allowedfileExtension=['jpeg','jpg','png','mp4', 'avi', 'flv', 'wmv', 'mpg', 'mpeg', '3gp'];

        foreach($request->file('media') as $fileValue)
        {
            $extension = $fileValue->getClientOriginalExtension();
            if(!in_array(strtolower($extension), $allowedfileExtension))
            return response()->json([
                'status' => 0,
                'message' => config('constant.FILE_TYPE_NOT_ALLOWED')
            ]);
        }
        $newAlbums = new Albums;
        $newAlbums->user_id = $user_id;
        $newAlbums->stories_id = $stories_id;
        $newAlbums->name = $name;
        $newAlbums->date =  $request->date;
        $newAlbums->place = $request->place;
        $newAlbums->description = isset($request->description)?$request->description:'';
        
        $newAlbums->save();
       
        $album_id = $newAlbums->id;

        // News Create
        $note = $request->user->firstName.' has created a album '.$name;
              $arr = array (
                   'user_id'=>$request->user->id,
                   'note'=>$note,
                   'description'=>$note,
                   'album_id'=>$album_id
              );
            
         $this->addNews($arr);

        $album_images_array = array();
        foreach($request->file('media') as $file)
        {
            $filename = $file->getClientOriginalName();
            $file->getRealPath();
            //Display File Mime Type
            $filename = time().$filename;
            array_push($album_images_array, array('album_id'=>$album_id, 'user_id'=> $user_id, 'album_media_path'=>$filename));
            //Move Uploaded File
            $file->move(public_path('album_images'),$filename);
        }
        AlbumImages::insert($album_images_array);
        return response()->json([
            'status' => 1,
            'message' => config('constant.ALBUM_SAVED_SUCCESS')
        ]);
    }

    public function userAlbumList(Request $request)
    {
        $user_id = $request->user->id;
        $userAlbums = Albums::where('user_id', '=', $user_id)->get()->toArray();
        
       
        if(!count($userAlbums))
            return response()->json([
            'status' => 0,
            'message' => config('constant.NO_ALBUM_FOUND')
        ]);
        $img_path = URL("public/album_images");
        foreach ($userAlbums as $key => $value) {
            $stories = Stories::select('id', 'stories_name')->where('id',$value['stories_id'])->orderBy('id','desc')->first()->toArray();
            $userImages = AlbumImages::select('id', DB::Raw('CONCAT("'.$img_path.'","/", album_media_path) as album_media_path'))->where('album_id', '=', $value['id'])->get()->toArray();
            $userAlbums[$key]['stories'] = $stories;
            $userAlbums[$key]['album_images'] = $userImages;
        }
        

        return response()->json([
            'status' => 1,
            'message' => config('constant.ALBUM_LIST'),
            'data'=>$userAlbums
        ]);
    }

  

    public function updateAlbum(Request $request)
    {
        $id =  $request->id;
        if(isset($request->date))
        $dataToUpdate = ['date'=>$request->date];
        else if(isset($request->place))
        $dataToUpdate = ['place'=>$request->place];
        else
        return response()->json([
            'status' => 0,
            'message' => config('constant.PARAMETER_MISSING')
        ]);
        
        if(!isset($request->id))
            return response()->json([
                'status' => 0,
                'message' => config('constant.PARAMETER_MISSING')
            ]);
        $whereArr = [['id', '=', $id],['user_id', '=', $request->user->id]];
        if( Albums::where($whereArr)->first() ){
            $updated = Albums::where($whereArr)->update($dataToUpdate);
              

            if($updated){
                    // News Create
                $note = $request->user->firstName.' has updated a album ';
                $arr = array (
                    'user_id'=>$request->user->id,
                    'note'=>$note,
                    'description'=>$note,
                    'album_id'=>$id
                );
                $this->addNews($arr);
                return response()->json([
                    'status' => 1,
                    'message' => config('constant.ALBUM_UPDATED')
                ]);
            } else {
                return response()->json([
                    'status' => 1,
                    'message' => config('constant.SOMETHING_WENT_WRONG')
                ]);
            }
            
           
           
           
        }else{
            return response()->json([
                'status' => 0,
                'message' => config('constant.NO_ALBUM_FOUND')
            ]);
        }
    }

    public function deleteAlbum(Request $request)
    {
        $id =  $request->id;
        if(!isset($request->id))
            return response()->json([
                'status' => 0,
                'message' => config('constant.PARAMETER_MISSING')
            ]);
        $whereArr = [['id', '=', $id],['user_id', '=', $request->user->id]];
        if( Albums::where($whereArr)->first() ){
            $deleted = Albums::where($whereArr)->delete();
            
            if($deleted){
                   // News Create
                   $note = $request->user->firstName.' has deleted a album';
                   $arr = array (
                       'user_id'=>$request->user->id,
                       'note'=>$note,
                       'description'=>$note,
                       'album_id'=>null
                   );
                   $this->addNews($arr);

                $AlbumImages = AlbumImages::where('album_id', '=', $id)->get();
                foreach ($AlbumImages as $value) {
                    if(\File::exists(public_path('album_images/'.$value->album_media_path))){
                        \File::delete(public_path('album_images/'.$value->album_media_path));
                    }
                }
                AlbumImages::where('album_id', '=', $id)->delete();
                return response()->json([
                    'status' => 1,
                    'message' => config('constant.ALBUM_DELETED')
                ]);
            }
            else
            return response()->json([
                'status' => 1,
                'message' => config('constant.SOMETHING_WENT_WRONG')
            ]);
        }else{
            return response()->json([
                'status' => 0,
                'message' => config('constant.NO_ALBUM_FOUND')
            ]);
        }
    }


    public function storiesCreate(Request $request){

        if(!isset($request->stories_name))
            return response()->json([
                'status' => 0,
                'message' => config('constant.PARAMETER_MISSING')
            ]);
          
           
          $user_id = $request->user->id;
          $name = $request->user->firstName;
          $storyName = $request->stories_name;

          $filter = Stories::where('user_id',$user_id)->where('stories_name',$request->stories_name)->count();

           if($filter) {
            return response()->json([
                'status' => 0,
                'message' => config('constant.STORY_DUPLICATE'),
            ]);
           }

           

          $stories = array('user_id'=>$user_id,'stories_name'=>$request->stories_name);
          $insert = Stories::create($stories);

          if($insert) {
            $note = $name.' has created a story '.$storyName;
              $arr = array (
                   'user_id'=>$request->user->id,
                   'note'=>$note,
                   'description'=>$note,
                   'stories_id'=>$insert->id
              );
            
              $this->addNews($arr);
              
          }



        return response()->json([
            'status' => 1,
            'message' => config('constant.STORY_SAVED_SUCCESS'),
            'data'=>$insert
            
        ]);
            
            
    }

    public function getStories(Request $request){

        if(!isset($request->user->id))
            return response()->json([
                'status' => 0,
                'message' => config('constant.PARAMETER_MISSING')
            ]);
          

          $user_id = $request->user->id;
          $stories = Stories::select('id','user_id','stories_name AS name','created_at')->where('user_id',$user_id)->orderBy('id','desc')->get()->toArray();
    
           if($stories) {
            return response()->json([
                'status' => 1,
                'message' => config('constant.GET_STORIES'),
                'data' =>$stories,  
            ]);
           } else {
            return response()->json([
                'status' => 0,
                'message' => 'Record not found.',
                'data' =>array(),  
            ]);
           }
   
            
    }

    public function userStoriesList(Request $request)
    {  
        $user_id = $request->user->id;
        $stories_id = $request->stories_id;
        $userAlbums = Albums::where('user_id', '=', $user_id)->where('stories_id', '=', $stories_id)->orderBy('id','desc')->get()->toArray();
      
       
        if(!count($userAlbums))
            return response()->json([
            'status' => 0,
            'message' => config('constant.NO_ALBUM_FOUND')
        ]);
        $img_path = URL("public/album_images");
        foreach ($userAlbums as $key => $value) {
            $userImages = AlbumImages::select('id', DB::Raw('CONCAT("'.$img_path.'","/", album_media_path) as album_media_path'))->where('album_id', '=', $value['id'])->get()->toArray();
            $userAlbums[$key]['album_images'] = $userImages;
        }
       
        
       

        return response()->json([
            'status' => 1,
            'message' => config('constant.ALBUM_LIST'),
            'data'=>$userAlbums
        ]);
       
    }

   
    public function getNews(Request $request){
        if(!$request->user->id){
          return response()->json([
              'status' => 0,
              'message' => config('constant.PARAMETER_MISSING')
          ]);
        }
       
        $user_id = $request->user->id;

        $FollowedList = DB::table('followers')->Join('users', 'users.id', '=', 'followers.following_id')->select('users.id')->where('follower_id','=',$user_id)
                                            ->where('status','=',1)->get();
                                            
            $ids = array();
            if(count($FollowedList)>0){
                foreach($FollowedList as $key=>$val){
                    $ids[] = $val->id;
                }
            }                                  
           
         $news_list = DB::table('newsfeed')->whereIn('user_id', $ids)->orderBy('id','desc')->get();
          $ret = array();
         
         if(count($news_list)>0){
           
            foreach ($news_list as $key => $value) {
                $ret[$key]['news_title'] = $value->note;
                if($value->album_id) {
                   $ret[$key]['album'] = $this->getNewsDeatils($value->album_id);
                } else {
                    $ret[$key]['album'] = '';
                }
               
                
            }

         }

      return response()->json([
          'status' => 1,
          'message' => config('constant.GETFEED'),
          'data'=>$ret,
      ]);
         
  } 

    public function getNewsDeatils($album_id){
            if($album_id) {
                $userAlbums = Albums::where('id', '=', $album_id)->get()->first();
                if(!$userAlbums)
                return '';
                
                $img_path = URL("public/album_images");
                $stories = Stories::select('id', 'stories_name')->where('id',$userAlbums->stories_id)->orderBy('id','desc')->first();
                    if($userAlbums->stories_id){
                        $userAlbums['stories'] = $stories;
                    } else {
                        $userAlbums['stories'] = "";
                    }
                   
                    
                    $m = new \Moment\Moment();
                    $userAlbums['date'] = time_elapsed($userAlbums->created_at);
                
                    $userImages = AlbumImages::select('id', DB::Raw('CONCAT("'.$img_path.'","/", album_media_path) as album_media_path'))->where('album_id', '=', $userAlbums->id)->get()->toArray();
                    $userAlbums['album_images'] = $userImages;
                

                return $userAlbums;
            } 
     

       
        }


    public function addNews($arr){
          if(!count($arr)){
            return response()->json([
                'status' => 0,
                'message' => config('constant.PARAMETER_MISSING')
            ]);
          }
          
         
          $feed = NewsFeed::create($arr);

        // return response()->json([
        //     'status' => 1,
        //     'message' => config('constant.NEWSFEED'),
        //     'data'=>$feed
            
        // ]);
           
    }


}// Controller Class closing

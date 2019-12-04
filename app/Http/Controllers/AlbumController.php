<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Temporaryuploads;
use App\Albums;
use App\AlbumImages;
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
        if(!$request->hasFile('media') || !isset($request->name) || !isset($request->place) || !isset($request->date))
            return response()->json([
                'status' => 0,
                'message' => config('constant.PARAMETER_MISSING')
            ]);

        $user_id = $request->user->id;
        $name = $request->name;
        $isAlbumAlreadyExist = Albums::where('name', '=', $name)->where('user_id', '=', $user_id)->first();
        if($isAlbumAlreadyExist)
        return response()->json([
            'status' => 1,
            'message' => config('constant.ALBUM_ALREADY_EXIST')
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
        $newAlbums->name = $name;
        $newAlbums->date =  $request->date;
        $newAlbums->place = $request->place;
        $newAlbums->description = isset($request->description)?$request->description:'';
        $newAlbums->save();
        $album_id = $newAlbums->id;

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
            $userImages = AlbumImages::select('id', DB::Raw('CONCAT("'.$img_path.'","/", album_media_path) as album_media_path'))->where('album_id', '=', $value['id'])->get()->toArray();
            $userAlbums[$key]['album_images'] = $userImages;
        }
        
        // $userAlbums = DB::select("SELECT albums.id, albums.name as albID, (SELECT GROUP_CONCAT(albumimages.album_media_path SEPARATOR ',') from albumimages WHERE albumimages.album_id = albID) as albumsImages FROM `albums` where user_id = $user_id");
        
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
            if($updated)
            return response()->json([
                'status' => 1,
                'message' => config('constant.ALBUM_UPDATED')
            ]);
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
          $filter = Stories::where('user_id',$user_id)->where('stories_name',$request->stories_name)->count();

           if($filter) {
            return response()->json([
                'status' => 0,
                'message' => config('constant.STORY_DUPLICATE'),
            ]);
           }

          $stories = array('user_id'=>$user_id,'stories_name'=>$request->stories_name);
          $insert = Stories::create($stories);

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
          $stories = Stories::where('user_id',$user_id)->get()->toArray();
    
        
        
       
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
        
        // $userAlbums = DB::select("SELECT albums.id, albums.name as albID, (SELECT GROUP_CONCAT(albumimages.album_media_path SEPARATOR ',') from albumimages WHERE albumimages.album_id = albID) as albumsImages FROM `albums` where user_id = $user_id");
        
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


}// Controller Class closing

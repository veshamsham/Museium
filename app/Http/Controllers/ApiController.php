<?php
namespace App\Http\Controllers;

use App\AlbumImages;
use App\User;
use App\Followers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\DB;

require "phpmailer/vendor/autoload.php"; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class ApiController extends Controller
{
    public $loginAfterSignUp = true;
 
    public function register(Request $request)
    {
        $isEmailExist = User::where('email', '=', $request->email)->first();
        if($isEmailExist)
        return response()->json([
            'status' => 0,
            'message' => config('constant.EMAIL_ALLREADY_EXIST')
        ], 200);
        $user = new User();
        $user->firstName = $request->firstName;
        $user->lastName = $request->lastName;
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->save();

        return response()->json([
            'status' => 1,
            'message' => config('constant.REGISTER_SUCCESS')
        ], 200);
    }
 
    public function login(Request $request)
    {
        $input = $request->only('email', 'password');
        $jwt_token = null;
        if (!$jwt_token = JWTAuth::attempt($input)) {
            return response()->json([
                'status' => 0,
                'message' => Config('constant.INVALID_CREDENTIAL'),
            ], 401);
        }
        $userData = User::select('id', 'firstName', 'lastName', 'email', 'google_id', 'facebook_id', 'followers', 'followings', 'profile_pic')->where('email', '=', $request->email)->first();
        return response()->json([
            'status' => 1,
            'token' => $jwt_token,
            'data'=> $userData
        ]);
    }

    public function userStatus(Request $request) {
       
        if(!isset($request->user_status)){
            return response()->json([
                'status' => 0,
                'message' => config('constant.ALL_FIELDS_MANDATORY'),
            ]);
        }
           
            $message = config('constant.USER_STATUS_SUCCESS');
            $update = User::where('id', '=', $request->user->id)->update(['user_status'=>$request->user_status]);
            if($update) {
                return response()->json([
                    'status' => 1,
                    'message' => config('constant.USER_STATUS_SUCCESS'),
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => config('constant.GET_ERROR'),
                ]);
            }
           
       
    }

    public function socialLogin(Request $request)
    {
        $input = $request->only('facebook_id', 'google_id', 'name', 'email', 'profile_pic');
        if(!isset($input['name']) || !isset($input['email'])){
            return response()->json([
                'status' => 0,
                'message' => config('constant.ALL_FIELDS_MANDATORY'),
            ]);
        }
        if(isset($input['facebook_id'])){
            $currentUser = $this->loginAction(true, $input['facebook_id'],$input);
        }else if(isset($input['google_id'])){
            $currentUser = $this->loginAction(false, $input['google_id'],$input);
        }else{
            return response()->json([
                'status' => 0,
                'message' => config('constant.LOGIN_ERROR'),
            ]);
        }
        
        return response()->json([
            'status'=>1,
            'data' => $currentUser
        ]);
    }

    public function loginAction($login_type, $social_value, $input)
    {
        $user = new User();
        if($login_type)
            $socialKey = 'facebook_id';
        else
            $socialKey = 'google_id';

        $profile_pic = isset($input['profile_pic'])?$input['profile_pic']:'';
        $getUserfromSocialId=User::where($socialKey,'=',$social_value)->first();
        $user->$socialKey = $social_value;
        if($getUserfromSocialId){
            User::where($socialKey,'=',$social_value)->update(['name'=>$input['name'], 'profile_pic'=>$profile_pic]);
            $generateToken = JWTAuth::fromUser($getUserfromSocialId);
        }else{
            $checkEmailExist = User::where('email','=',$input['email'])->first();
            if($checkEmailExist)
            {
                User::where('email','=',$input['email'])->update([$socialKey =>$social_value, 'name'=>$input['name'], 'profile_pic'=>$profile_pic]);
            }else{
                $user->$socialKey = $input[$socialKey];
                $user->name = $input['name'];
                $user->email = $input['email'];
                $user->profile_pic = $profile_pic;
                $user->save();
            }
            $getUserfromSocialId=User::where($socialKey,'=',$social_value)->first();
            $generateToken = JWTAuth::fromUser($getUserfromSocialId);
        }
        // User::where($socialKey,'=',$social_value)->update(['remember_token' =>$generateToken]);
        $getUserfromSocialId->token = $generateToken;
        return $getUserfromSocialId;
    }
 
    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate($request->user->token);
 
            return response()->json([
                'status' => 1,
                'message' => config('constant.LOGOUT_SUCCESS')
            ]);
        } catch (JWTException $exception) {
            return response()->json([
                'status' => 0,
                'message' => config('constant.LOGOUT_ERROR')
            ], 500);
        }
    }
 
    public function getUserProfile(Request $request)
    {
        return response()->json(['status' => 1,'data'=> $request->user]); 
    }

    public function changeProfilePic(Request $request)
    {
        if($request->hasFile('profile_pic')){
            $file = $request->file('profile_pic');
            $allowedfileExtension=['jpeg','jpg','png'];
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
            $file->move(public_path('profile_pics'),$filename);
            User::where('id', '=', $request->user->id)->update(['profile_pic' => URL("public/profile_pics/".$filename)]);
            return response()->json([
                'status' => 1,
                'message' => config('constant.PROFILE_PIC_CHANGED_SUCCESS')
            ]);
        }else{
            return response()->json([
                'status' => 0,
                'message' => config('constant.ALL_FIELDS_MANDATORY')
            ]);
        }
        
    } // uploadMedia Closing

    public function searchPeople(Request $request)
    {   
        if(isset($request->keyword)){
            $searchResult = User::select('name', 'id', 'profile_pic')->where('name','=',$request->keyword)
                                ->where('id','!=',$request->user->id)->get()->toArray();
            $searchResultIds = array();
            if($searchResult){
                foreach ($searchResult as $key => $value) {
                    $imagesCount = AlbumImages::where('user_id', '=', $value['id'])->count();
                    $isFollow = Followers::where([
                        ['following_id', '=', $value['id']],
                        ['follower_id', '=', $request->user->id],
                        ['status', '=', 1]
                        ])->first();
                        
                        if($isFollow)
                            $searchResult[$key]['follow_status'] = true;
                        else{
                            $searchResult[$key]['follow_status'] = false;
                        }
                    $searchResult[$key]['imageCount'] = $imagesCount;
                }
                $searchResultIds = array_column($searchResult, 'id');
            }
             // Getting only Ids
            array_push($searchResultIds, $request->user->id);
            $similarResult = User::select('name', 'id', 'profile_pic')->where('name', 'like', '%' . $request->keyword . '%')
                                    ->whereNotIn('id', $searchResultIds)->get()->toArray();
            foreach ($similarResult as $key1 => $value1) {
                $imagesCount = AlbumImages::where('user_id', '=', $value1['id'])->count();
                $isFollow = Followers::where([
                    ['following_id', '=', $value1['id']],
                    ['follower_id', '=', $request->user->id],
                    ['status', '=', 1]
                    ])->first();
                    
                    if($isFollow)
                        $similarResult[$key1]['follow_status'] = true;
                    else{
                        $similarResult[$key1]['follow_status'] = false;
                    }
                    $similarResult[$key1]['imageCount'] = $imagesCount;
            }
        }else{
            return response()->json([
                'status' => 0,
                'message' => config('constant.SEARCH_KEYWORD_NOT_PROVIDED'),
            ]);
        }
        if(count($searchResult) || count($similarResult))
        return response()->json([
            'status'=>1,
            'searchResult' => $searchResult,
            'similarResult' => $similarResult
        ]);
        else
        return response()->json([
            'status'=>1,
            'message' => config('constant.SEARCH_RESULT_NOT_FOUND'),
            'searchResult' => array(),
            'similarResult' =>array()
        ]);
    }

    public function followUser(Request $request)
    {   
        $follower_id = $request->user->id;
        $following_id = $request->user_id;
        $status = $request->status;
        if(isset($request->user_id) && isset($request->user->id) && isset($request->status)){
            $isFollowingUserExist = User::select('followings', 'followers')->where('id', '=', $following_id)->first();
            if(!$isFollowingUserExist)
            return response()->json([
                'status'=>0,
                'message' => config('constant.USER_NOT_EXIST')
            ]);
            
            $isFollowedExist = Followers::where('follower_id','=',$follower_id)
                                            ->where('following_id','=',$following_id)->first();
            if($isFollowedExist){
                Followers::where('follower_id','=',$follower_id)
                ->where('following_id','=',$following_id)->update(['status' =>$status]);
                
            }else{
                $followerModel = new Followers();
                $followerModel->follower_id = $follower_id;
                $followerModel->following_id = $following_id;
                $followerModel->status = $status;
                $followerModel->save();
            }
            if($status)
            {
                $message = config('constant.USER_FOLLOW_SUCCESS');
                User::where('id', '=', $following_id)->update(['followers'=>$isFollowingUserExist->followers+1]);
                User::where('id', '=', $follower_id)->update(['followings'=>$request->user->followings+1]);
            }else{
                $message = config('constant.USER_UNFOLLOW_SUCCESS');
                User::where('id', '=', $following_id)->update(['followers'=>$isFollowingUserExist->followers-1]);
                User::where('id', '=', $follower_id)->update(['followings'=>$request->user->followings-1]);
            }      
            return response()->json([
                'status'=>1,
                'message' => $message
            ]);    
        }else{
            return response()->json([
                'status' => 0,
                'message' => config('constant.ALL_FIELDS_MANDATORY'),
            ]);
        }
        
    }


    public function followUserRequestList(Request $request)
    {   
        $follower_id = $request->user->id;
    
        if(isset($request->user->id)){
            

            // $isFollowedExist = DB::table('followers')->leftJoin('users', 'users.id', '=', 'followers.follower_id')->where('following_id','=',$follower_id)
            //                                 ->where('status','=',0)->get();

        $isFollowedExist = DB::table('followers')->Join('users', 'users.id', '=', 'followers.following_id')->where('follower_id','=',$user_id)
        ->where('status','=',0)->get();
                                            
                                          
            
            return response()->json([
                'status'=>1,
                'message' => 'List of requested follow list.',
                'data'=>$isFollowedExist
            ]);    
        }else{
            return response()->json([
                'status' => 0,
                'message' => config('constant.ALL_FIELDS_MANDATORY'),
            ]);
        }
        
    }


    public function acceptFollow(Request $request)
    {   
        $follower_id = $request->user->id;
        $following_id = $request->user_id;
        $status = $request->status;
    
        if(isset($request->user_id) && isset($request->user->id) && isset($request->status)){
            
                $isFollowedExist = Followers::where('follower_id','=',$follower_id)
                            ->where('following_id','=',$following_id)->first();

           $isFollowingUserExist = User::select('followings', 'followers')->where('id', '=', $following_id)->first();

            if(!$isFollowingUserExist || !$isFollowedExist)
            return response()->json([
                'status'=>0,
                'message' => config('constant.USER_NOT_EXIST')
            ]);

               
                   Followers::where('follower_id','=',$follower_id)
                   ->where('following_id','=',$following_id)->update(['status' =>$status]);
                

            if($status)
            {
                $message = config('constant.ACCEPT_REQUEST');
                User::where('id', '=', $following_id)->update(['followers'=>$isFollowingUserExist->followers+1]);
                User::where('id', '=', $follower_id)->update(['followings'=>$request->user->followings+1]);
            }else{
                $message = config('constant.REJECT_REQUEST');
                User::where('id', '=', $following_id)->update(['followers'=>$isFollowingUserExist->followers-1]);
                User::where('id', '=', $follower_id)->update(['followings'=>$request->user->followings-1]);
            }
                                          
            
            return response()->json([
                'status'=>1,
                'message' => $message,
                //'data'=>$isFollowedExist
            ]);    
        }else{
            return response()->json([
                'status' => 0,
                'message' => config('constant.ALL_FIELDS_MANDATORY'),
            ]);
        }
        
    }

    public function getUserDetails(Request $request)
    {   
        if(isset($request->user_id)){
            $userDetails = User::select('name', 'id', 'profile_pic', 'followers', 'followings')->where('id','=',$request->user_id)->first();
            if($userDetails){
                $imagesCount = AlbumImages::where('user_id', '=', $userDetails->id)->count();
                $userDetails->imageCount = $imagesCount;
                $isFollowedExist = Followers::where('follower_id','=',$request->user->id)
                                            ->where('following_id','=',$request->user_id)
                                            ->where('status','=',1)->first();
                if($isFollowedExist)
                $userDetails->follow_status = true;
                else
                $userDetails->follow_status = false;
                if($imagesCount){
                    $img_path = URL("public/album_images");
                    $userMedia = AlbumImages::select('id', DB::Raw('CONCAT("'.$img_path.'","/", album_media_path) as album_media_path'))->where('user_id', '=', $userDetails->id)->get()->toArray();
                    $userDetails->userMedia = $userMedia;
                }
                return response()->json([
                    'status'=>1,
                    'data' => $userDetails
                ]);
            }else
            return response()->json([
                'status' => 1,
                'message' => config('constant.USER_NOT_EXIST'),
            ]);
            
        }else{
            return response()->json([
                'status' => 0,
                'message' => config('constant.PARAMETER_MISSING'),
            ]);
        }
    }

    public function forgotPassword(Request $request) {
         
        if(!$request->email) {
            return $result = collect(["status" => "0", "message" => "Please Provide me mail id.", 'errorCode' => '', 'errorDesc' => '', "data" => array()]);
        }
     
        $to = $request->email;

        $getUser = User::where('email', '=', $request->email)->first();
         
        if(!$getUser) {
            return $result = collect(["status" => "0", "message" => "Sorry !!! I could'nt have recognised record please enter correct email id.", 'errorCode' => '', 'errorDesc' => '', "data" => array()]);
         }
        
        $otp = rand(100000, 999999);
      
        $subject = 'Forgot Password.';
        $resMessage = "Your OTP has been send Successfully.";
        $message = '<div class="user_content_block">
                               <div class="allborder nopadding text-left padding10 user_content_inner" style="font-size:12px; font-family:Helvetica;" contenteditable="true">
                               <p>Hi ,' . $getUser->name . '</p>
                               <p></p>
                               <p>Your One Time OTP is <strong>' . $otp. '</strong></p>
                               <p><strong></strong></p>
                               <p><strong>Thanks</strong><br>
                               </p>
                               </div>
                               </div>';
                               
           $mail =  $this->sendMail($to,$message,$subject,$resMessage);
           if($mail) {
               $update = User::where('id','=',$getUser->id)->update(['verification_otp'=>$otp]);
               return $result = collect(["status" => "1", "message" => $resMessage, 'errorCode' => '', 'errorDesc' => '', "data" => array()]);
           } else {
            return $result = collect(["status" => "0", "message" => "Oops Something went wrong.", 'errorCode' => '', 'errorDesc' => '', "data" => array()]);
           } 
     
}

public function resendOtp(Request $request) {
         
    if(!$request->email) {
        return $result = collect(["status" => "0", "message" => "Please Provide me mail id.", 'errorCode' => '', 'errorDesc' => '', "data" => array()]);
    }
 
    $to = $request->email;

    $getUser = User::where('email', '=', $request->email)->first();
     
    if(!$getUser) {
        return $result = collect(["status" => "0", "message" => "Sorry !!! I could'nt have recognised record please enter correct email id.", 'errorCode' => '', 'errorDesc' => '', "data" => array()]);
     }
    
    $otp = rand(100000, 999999);
  
    $subject = 'Resend OTP.';
    $resMessage = "Your OTP has been send Successfully.";
    $message = '<div class="user_content_block">
                           <div class="allborder nopadding text-left padding10 user_content_inner" style="font-size:12px; font-family:Helvetica;" contenteditable="true">
                           <p>Hi ,' . $getUser->name . '</p>
                           <p></p>
                           <p>Your One Time OTP is <strong>' . $otp. '</strong></p>
                           <p><strong></strong></p>
                           <p><strong>Thanks</strong><br>
                           </p>
                           </div>
                           </div>';
                           
       $mail =  $this->sendMail($to,$message,$subject,$resMessage);
       if($mail) {
           $update = User::where('id','=',$getUser->id)->update(['verification_otp'=>$otp]);
           return $result = collect(["status" => "1", "message" => $resMessage, 'errorCode' => '', 'errorDesc' => '', "data" => array()]);
       } else {
        return $result = collect(["status" => "0", "message" => "Oops Something went wrong.", 'errorCode' => '', 'errorDesc' => '', "data" => array()]);
       } 
 
}



    public function verificationOtp(Request $request){
        if(!$request->email || !$request->otp) {
            return $result = collect(["status" => "0", "message" => "Please Provide me mail id or otp", 'errorCode' => '', 'errorDesc' => '', "data" => array()]);
        }
    
        $to = $request->email;

        $getUser = User::where('email', '=', $request->email)->where('verification_otp', '=', $request->otp)->first();
        
        if(!$getUser) {
            return $result = collect(["status" => "0", "message" => "Please enter correct OTP.", 'errorCode' => '', 'errorDesc' => '', "data" => array()]);
        } else {
            return $result = collect(["status" => "1", "message" => "You have successfully verified OTP.", 'errorCode' => '', 'errorDesc' => '', "data" => array()]);
        }

        
    }


        //Changed or update password
        public function update_Password(Request $request) {
             
             if(!$request->new_password || !$request->confirm_password) {
                $result = collect(["status" => "2", "message" => 'Please Provide me confirm_password and new_password.', 'errorCode' => '', 'errorDesc' => '', "data" => new \stdClass()]);
                return $result;  
             }
            if (trim($request->new_password) && trim($request->confirm_password) && trim($request->id)) {
            
                $user  = User::where('id', $request->id)->get();
                    if ($request->new_password == $request->confirm_password) {
                        User::where('id', trim($request->id))->update(['password' => bcrypt($request->new_password)]);
                        $result = collect(["status" => "1", "message" => 'Your password has been changed.', 'errorCode' => '', 'errorDesc' => '', "data" => $user]);
                        return $result;
                    } else {
                        $result = collect(["status" => "2", "message" => 'Confirm passowrd is not match.', 'errorCode' => '', 'errorDesc' => '', "data" => new \stdClass()]);
                        return $result;
                    }
            
        } else {
            $result = collect(["status" => "2", "message" => 'Please Provide me all request.', 'errorCode' => '', 'errorDesc' => '', "data" => new \stdClass()]);
            return $result;
        } 
    }


    
        //Users FOllers Request
//         public function followRequest(Request $request) {
             
//             if(!isset($request->follow_id)) {
//                 return response()->json([
//                     'status' => 0,
//                     'message' => config('constant.ALL_FIELDS_MANDATORY'),
//                 ]); 
//             }
//            if (trim($request->follow_id)) {
           
//                $user  = User::where('id', $request->id)->get();
//                    if ($request->new_password == $request->confirm_password) {
//                        User::where('id', trim($request->id))->update(['password' => bcrypt($request->new_password)]);
//                        $result = collect(["status" => "1", "message" => 'Your password has been changed.', 'errorCode' => '', 'errorDesc' => '', "data" => $user]);
//                        return $result;
//                    } else {
//                        $result = collect(["status" => "2", "message" => 'Confirm passowrd is not match.', 'errorCode' => '', 'errorDesc' => '', "data" => new \stdClass()]);
//                        return $result;
//                    }
           
//        } else {
//            $result = collect(["status" => "2", "message" => 'Please Provide me all request.', 'errorCode' => '', 'errorDesc' => '', "data" => new \stdClass()]);
//            return $result;
//        } 
//    }

    






    public function sendMail($to,$message,$subject,$resMessage){

        $developmentMode = true;
        $mailer = new PHPMailer($developmentMode);

        try {
            $mailer->SMTPDebug = 0;
            $mailer->isSMTP();
            if ($developmentMode) {
                $mailer->SMTPOptions = [
                    'ssl'=> [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
            }

            $mailer->Host = 'smtp.gmail.com';
            $mailer->SMTPAuth = true;
            $mailer->Username = 'madteamve@gmail.com';
            $mailer->Password = 'qwerty1Q#$';
            $mailer->SMTPSecure = 'tls';
            $mailer->Port = 587;
           
            $mailer->setFrom('support@museum.com', 'admin');
            $mailer->addAddress($to, '');
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $message;
            $mailer->send();
            $mailer->ClearAllRecipients();
            
            return true;
        

        } catch (Exception $e) {
            return false;
        
        }


    }



}

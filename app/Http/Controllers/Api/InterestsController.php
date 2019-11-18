<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use App\Interest;
//use App\Http\Controllers\Classes\Firebase;

class InterestsController extends Controller
{
    /* Send Interest */
    public function sendInterest(Request $request)
    {
        # code...
         $validator = Validator::make($request->all(), [
            'user_id' => "required|numeric",
            'requested_id' => "required|numeric",
        ]);
          
        if ($validator->fails()) {
            return Response::fail($validator->errors()->first());
        }

        $data =[
                "user_id" => $request->user_id,
                "requested_id" => $request->requested_id
        ];
        $data2 =[
                "user_id" => $request->requested_id,
                "requested_id" => $request->user_id
        ];
          //$send_interest = Interest::firstOrCreate($data);
         // \DB::enableQueryLog();
if($request->user_id == $request->requested_id)  return Response::fail("You cannot send interest to yourself");
 
/* check Profile Completion */
$me = CommonQuery::getMainQuery()
            ->where('users.id', $request->user_id)
            ->first();
  
 $x = $me->toArray();
    $remaining_fields = $total_fields = 0;
   // wasRecentlyCreated
      foreach ($x as  $key => $value) {
          if($value === "" || is_null($value)){
             // dd($key);
             $remaining_fields++;
          }
          $total_fields++;
      }



      $profile_completion = (int)(100 - round(($remaining_fields/$total_fields)*100));
    
      if($profile_completion < 70) return Response::fail("Your profile is not complete.");
      if(is_null($me->avatar_thumb) || strchr($me->avatar_thumb,'/users/default-user.png')) return Response::fail("Please upload your profile picture first.");



/* count sent request */
         $sent_requests = Interest::where("user_id","=",$request->user_id)
                                    ->count(); 
         if($sent_requests > 99) return Response::fail('Your profile is limited to 100 Interest requests');
      /* end */

        $get_interest = Interest::where($data)
                                   ->orWhere(\DB::raw("(user_id=$request->requested_id and requested_id=$request->user_id)"))->get();
                                 // dd( \DB::getQueryLog());
if($get_interest->count()===0){
  if(Interest::create($data)):
  
  $msg = [
        'title' => "You Recieved New Interest",
    'body'  => $me->name." has sent you a Request",
         ];
         
         

         $fb_token = \DB::table('firebase')
                        ->select('firebase_token')
                        ->where('user_id','=',$request->requested_id)->first();
             //file_put_contents("test.txt",json_encode([$fb_token->firebase_token])."\n",FILE_APPEND);
             
             //added variable by sanath
             $title="Interest Expressed";
         $message=$me->name." has sent you a Request";
             
          if(!is_null($fb_token))
          $this->push_notification_android($fb_token->firebase_token,$title,$message);
            //called by sanath
            //Firebase::send([$fb_token->firebase_token], $msg);
            
          
          

          return Response::pass((99 - $sent_requests)." Request remaining");
        endif;


    }
    
    return Response::fail("Already Sent");
          
    }
    
    
    //function added by sanath
    
    function push_notification_android($device_id,$title,$message){

    //API URL of FCM
    $url = 'https://fcm.googleapis.com/fcm/send';

    /*api_key available in:
    Firebase Console -> Project Settings -> CLOUD MESSAGING -> Server key*/    $api_key = 'AAAAzqOinLI:APA91bErrL4QOqX4_neZg1QOFPvwod2wCiAlwdtQ6c9e_NP7IOnGGHXGL9u8iv3ImwNl_Sxs8yn74V_G6Y3zBkuyBn7xampyqgBj2memu_z1vnuiwTnAwe8z04LtVlLRZ5IQux1FTHgU';
                
    $fields = array (
        'registration_ids' => array (
                $device_id
        ),
        'data' => array (
                "message" => $message,
                "priority"=> "high",
                "title"=> $title
        )
    );

    //header includes Content type and api key
    $headers = array(
        'Content-Type:application/json',
        'Authorization:key='.$api_key
    );
                
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    $result = curl_exec($ch);
    if ($result === FALSE) {
        die('FCM Send Error: ' . curl_error($ch));
    }
    curl_close($ch);
    return $result;
}

    
    
    
    
    
    
    

 /* sent Interest */

    public function getInterest(Request $request)
    {   
          if($user = auth('api')->user())
                {
                    $status = $user->is_active;
                    if($status == "2")  return Response::fail("Account has been Deleted");
                    if($status == "3") return Response::fail("Account has been Deactivated By Admin");
                    if($status == "0") return Response::fail("Account is not Activated");
        }
       $validator = Validator::make($request->all(), [
            'user_id' => "required|numeric",
            'gender' => "required|alpha|max:1",
            'type'  =>  "required"
        ]);
          
        if ($validator->fails()) {
            return Response::fail($validator->errors()->first());
        }
        $sid = (new UserApiController)->shortListed($request,1);
        $interest = $this->allInterest($request->user_id);

        $users_ids = [];
        if($request->type == "sent") $users_ids = $interest['sent']; 
        if($request->type == "recieved") $users_ids = $interest['recieved']; 
        
       $gender = (strtoupper($request->gender) == 'M') ? 'F' : 'M';
       $interest_data = CommonQuery::getMainQuery()
                       ->whereIn('users.id',$users_ids)
                       ->where(['gender' => $gender, 'is_active' => 1])
                       ->simplePaginate();
       
       if($interest_data->count()===0) return Response::fail('No candidates available'); 

          $user_data = CommonQuery::addParameter($interest_data->items(), $sid, $interest);

        return response()->json([
            "success" => true,
            "message" => "Candidates available",
            "status"  => auth('api')->user()->is_active,
            "data" => $user_data,
            "next_page_url" => (is_null($interest_data->nextPageUrl())) ? "" : $interest_data->nextPageUrl(),
            "per_page" => $interest_data->perPage(),
            "prev_page_url" => (is_null($interest_data->previousPageUrl())) ? "" : $interest_data->previousPageUrl(),
            "has_more_pages" => $interest_data->hasMorePages(),
            "current_url" => $interest_data->url($interest_data->currentPage()),
            "current_page" => $interest_data->currentPage(),
        ]);       

    }



/* Update request status */
    public function updateInterestStatus(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'user_id' => "required|numeric",
            'requested_id' => "required|numeric",
        ]);
          
        if ($validator->fails()) {
            return Response::fail($validator->errors()->first());
        }

       $accept = Interest::where(["user_id" => $request->requested_id, 'requested_id' => $request->user_id])->update(['status' => 1]);

       if($accept){

            $msg = [
        'title' => "Request Confirmation",
    'body'  => "Your request has been accepted"
         ];

         $fb_token = \DB::table('firebase')
                        ->select('firebase_token')
                        ->where('user_id','=',$request->requested_id)->first();
          
          //variables added by sanath
          $me = CommonQuery::getMainQuery()
            ->where('users.id', $request->user_id)
            ->first();
          $title="Interest Accepted";
          $message="Your request has been accepted by ".$me->name;
          
          if(!is_null($fb_token))   
          $this->push_notification_android($fb_token->firebase_token,$title,$message);
          //Firebase::send([$fb_token->firebase_token], $msg);    


          return Response::pass('Accepted'); 
          
          
        }
        return Response::fail('Try Again'); 
        # code...
    }



    public function allInterest($user_id = 0)
    {
        $interest = Interest::where(['user_id' => $user_id])
                     ->orWhere(['requested_id' => $user_id])
                     ->select('user_id','requested_id', 'status')
                     ->orderBy('id','desc')
                     ->get();
                      $status = $sent = $recieved = [];
                      if($interest->count()>0):
       foreach ($interest as $key => $value) 
       {
           if($user_id == $value->requested_id)
            { 
              $recieved[] = $value->user_id;
              $status[$value->user_id] = $value->status;
           }
           if($user_id == $value->user_id)
           { 
              $sent[] = $value->requested_id;
              $status[$value->requested_id] = $value->status;
           }   
       }
      endif;
      return ['sent' => $sent, 'recieved' => $recieved, 'status' => $status];
     
    }


    /* Cancel request */
    public function cancelInterest(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'user_id' => "required|numeric",
            'requested_id' => "required|numeric",
        ]);
          
        if ($validator->fails()) {
            return Response::fail($validator->errors()->first());
        }
        
         $data =[
                "user_id" => $request->user_id,
                "requested_id" => $request->requested_id
        ];
        
        $cancel = Interest::where($data)->delete();
        if($cancel) return Response::pass("Request cancelled");
        return Response::fail("Try again");
    }

}

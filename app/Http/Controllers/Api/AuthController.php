<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\User;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\UserRegisterValidation;
use Illuminate\Support\Facades\Mail;
use App\Mail\RegistrationSuccessfull;
use DB;
use App\Http\Requests\UpdateRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

use App\Interest;

class AuthController extends Controller
{
    var $msg;
    protected $images = [];
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request, int $x=0)
    {
        //dd(request());
        $validator = Validator::make($request->all(), [
            'mobile' => "required|numeric|digits:10",
            "password" => "required",
        ]);

        if ($validator->fails()) {
            return Response::fail($validator->errors()->first());
        }


        $credentials = request(['mobile', 'password']);

        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return Response::fail('Invalid User or Password');
        }
       
        return $this->respondWithToken($token, $x, $request->firebase_token );
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(int $t = 0, $user_id = 0)
    {
        //DB::enableQueryLog();
         $user = auth('api')->user();
         
          if($t === 1){
        $me = CommonQuery::getMainQuery()
            ->where('users.id', $user_id)
            ->first();
          }
          
          else {
        $me = CommonQuery::getMainQuery()
            ->where('users.id', $user->id)
            ->first();
          }
                   
        if (collect($me)->isEmpty()) {
            return Response::fail('User not found');
        }
        
       if($t === 0 && $me->is_active == "0") return Response::fail("Activate Your Account from Email");
        if($t === 0 && $me->is_active == "3") return Response::fail("Your Account has been disabled");
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



      $me->profile_completion = (string)(100 - round(($remaining_fields/$total_fields)*100));

            if($me->lang ==="hi"){ 
                $me->state = $me->state_hi;
                $me->district = $me->district_hi; 
                $me->occupation = $me->occupation_hi; 
                $me->marital_status = $me->marital_status_hi;
                $me->manglik = $me->manglik_hi;
            }
      

      if($t === 1) return $me;
        return Response::pass('User found', $me);
    }


    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return Response::pass('Successfully logged out');
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token, int $x = 0, $firebase_token = null)
    {
        $user = auth('api')->user();
        if($x === 0 && $user->is_active == "0") return Response::fail("Activate Your Account from Email");
        if($x === 0 && $user->is_active == "3") return Response::fail("Your Account has been disabled");
        
        /* set firebase token */
      if(!is_null($firebase_token)){
         $fb = DB::table('firebase')->where('user_id', $user->id)->first();
         if(is_null($fb)){
             DB::table('firebase')->insert(['user_id' => $user->id, 'firebase_token' => $firebase_token ]);
         }
         else{
             DB::table('firebase')->where(['user_id' => $user->id])->update(['firebase_token' => $firebase_token]);
         }

       }
        
        $me = $this->me(1,$user->id);
        
         return response()->json([
            "success" => true,
            "message" => "Logged in Successfully",
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'data' => $me
        ]);
    }

    /* Register User */
    public function register(UserRegisterValidation $request)
    {
          $data = $request->all();
          
        if($data['working'] == 0){
            $data['income'] = 0;
            $data['occupation']= 0;
        }
               

         //file_put_contents("test.txt",json_encode($request->all())."\n",FILE_APPEND);
         //die;
         
         
         //gupshup sms api added by sanath
       
        
        //message to client 
        $msg="Hi, new member joined \nName:".$request->name."\nPhone :".$request->mobile;
        $this->sendsms("7483632034",$msg);
        
        //message to user
        $msg="Dear ".$request->name." , Thank you for registering at Malnad matrimony. Kindly upload your photo. Photo upload is mandatory.\n For help call 7483632034 \n
-Thank you";
        $this->sendsms($request->mobile,$msg);
        
        //otp to user
         $msg = "Use " . $data['otp'] . " as the code to verify your phone number on Malnad Matrimony";
        $this->sendsms($request->mobile,$msg);
        
        //message to all users
        if($request->gender == "M")
        {
            $msg="Hi,  ".$request->name." has joined Malnad Matrimony just now. Login to app & view his  profile. Don't miss out New matches that meet your preferences. For help call  7483632034";
            $numbers = DB::select('select mobile from users where gender = :gender', ['gender' => "F"]);
            foreach($numbers as $row)
            {
                $this->sendsms($row->mobile,$msg);
            }
        }
        else
        {
            $msg="Hi,  ".$request->name." has joined Malnad Matrimony just now. Login to app & view her profile. Don't miss out New matches that meet your preferences. For help call  7483632034";
            $numbers = DB::select('select mobile from users where gender = :gender', ['gender' => "M"]);
            foreach($numbers as $row)
            {
                $this->sendsms($row->mobile,$msg);
            }
        }
        
        
        //ends here
        
        
        
        
        unset($data['otp']);
        $data['verify_token'] = Str::random(60);
        $user = User::create($data)->count();
        if ($user > 0) :
           
        return $this->login($request, 1);
        endif;

        return Response::fail("Registration Failed");
    }

/* Reset Password */
public function passwordReset(Request $request)
{
     $validator = Validator::make($request->all(), [
            'old_password' => "required",
            "new_password" => "required",
        ]);
        if ($validator->fails()) {
            return Response::fail($validator->errors()->first());
        }
        
        $user = auth('api')->user();
       // dd($request->old_password ." - ".$user->password);
        if(Hash::check($request->old_password, $user->password)){
             $user->fill([
            'password' => $request->new_password
        ])->save();
             return Response::pass("Password reset Successfull");
        }
        return Response::fail("Password reset failed");
}


/* forget password */

public function forgetPassword(Request $request)
{
    $validator = Validator::make($request->all(), [
            'mobile' => "required|numeric|digits:10"
        ]);
        if ($validator->fails()) {
            return Response::fail($validator->errors()->first());
        }

        $user = User::where('mobile', '=', $request->mobile)->first();
        if(is_null($user)) return Response::fail('User does\'nt exist');
        $password = rand(100000, 999999);  
        $user->password = $password;
        
        if($user->save()){
            $msg = "Your new login password for MalnadMatrimony is $password please change after login."; 
      
        //gupshup sms api added by sanath
        
        $this->sendsms($request->mobile,$msg);
        
        //ends here
        
        
        return Response::pass("Password Sent");
        }
       return  Response::fail("Something went wrong");
}

    public function sendMail(Request $request)
    {
       $data = $request->all();
       $data['verify_token'] = Str::random(60);
        # code...
         Mail::send(new RegistrationSuccessfull($data));
    }
    
    //function added by sanath gupshup sms api
    public function sendsms($to,$msg)
    {
        $var =""; //initialise the request variable 
        $param['method']= "sendMessage";
        $param['send_to'] = "91".$to;
        $param['msg'] = $msg;
        $param['userid'] = "2000188168";
        $param['password'] ="hHgXN3J0k";
        $param['v'] = "1.1";
        $param['msg_type'] = "TEXT"; //Can be "FLASH”/"UNICODE_TEXT"/”BINARY”
        $param['auth_scheme'] = "PLAIN";//Have to URL encode the values 
        foreach($param as $key=>$val)
        {
            $var.= $key."=".urlencode($val); //we have to urlencode the values 
            $var.= "&";//append the ampersand (&) sign after each parameter/value pair
        }
        $var = substr($var, 0, strlen($var)-1); //remove final (&) sign from the request
        $url = "http://enterprise.smsgupshup.com/GatewayAPI/rest?".$var;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $curl_scraped_page = curl_exec($ch);
        curl_close($ch);
       // echo $curl_scraped_page;
    }
    
    public function versioncode()
    {
        $result['versioncode']=29;
        echo json_encode($result);
    }
    
    public function test()
    {
        $numbers = DB::select('select mobile from users where gender = :gender', ['gender' => "M"]);
        foreach($numbers as $row)
        {
            echo $row->mobile."\n";
        }
        //print_r($results);
    }
    
    public function pushNotification(Request $request)
    {
        $name = $request->input('name');
        $message = $request->input('message');
        $sender = $request->input('sender');
        $user_token = DB::select("select firebase_token from firebase f,users u where u.id=f.user_id and u.name='$name'");
        foreach($user_token as $row)
        {
            $token= $row->firebase_token;
        }
        
         //API URL of FCM
        $url = 'https://fcm.googleapis.com/fcm/send';

         /*api_key available in:
        Firebase Console -> Project Settings -> CLOUD MESSAGING -> Server key*/    $api_key = 'AAAAzqOinLI:APA91bErrL4QOqX4_neZg1QOFPvwod2wCiAlwdtQ6c9e_NP7IOnGGHXGL9u8iv3ImwNl_Sxs8yn74V_G6Y3zBkuyBn7xampyqgBj2memu_z1vnuiwTnAwe8z04LtVlLRZ5IQux1FTHgU';
                
        $fields = array (
         'registration_ids' => array (
                   $token
            ),
        'data' => array (
                "message" => $message,
                "priority"=> "high",
                "title"=> "New Message From ".$sender
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
        echo $result;
    }
    
    public function rejectInterest(Request $request)
    {
        $user_id = $request->input('user_id');
        $requested_id = $request->input('requested_id');
        echo "user_id : ".$user_id." requested_id : ".$requested_id."<br/>";
       // $result = DB::select("");
       if($user_id != null && $requested_id != null)
       {
           $result = Interest::where(["user_id" => $user_id, 'requested_id' => $requested_id])->update(['status' => 0]);
            echo "result : ".$result;
       }
        
    }

    
}



//added by sanath msg91 api
//Your authentication key
// $authKey = "263914A1RjGciX5c6d182f";

// //Multiple mobiles numbers separated by comma
//  $number=$request->mobile;

// //Sender ID,While using route4 sender id should be 6 characters long.
// $senderId = "MBSMLD";

// //Your message to send, Add URL encoding here.
// $message = urlencode("$msg");

// //Define route 
// $route = 4;


// $curl = curl_init();

// curl_setopt_array($curl, array(
//   CURLOPT_URL => "https://api.msg91.com/api/sendhttp.php?mobiles=".$number."&authkey=".$authKey."&route=".$route."&sender=".$senderId."&message=".$msg."&country=91",
//   CURLOPT_RETURNTRANSFER => true,
//   CURLOPT_ENCODING => "",
//   CURLOPT_MAXREDIRS => 10,
//   CURLOPT_TIMEOUT => 30,
//   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//   CURLOPT_CUSTOMREQUEST => "GET",
//   CURLOPT_SSL_VERIFYHOST => 0,
//   CURLOPT_SSL_VERIFYPEER => 0,
// ));

// $response = curl_exec($curl);
// $err = curl_error($curl);

// curl_close($curl);

// if ($err) {
//   //echo "cURL Error #:" . $err;
// } else {
//   //echo $response;
// }

        //ends here
        


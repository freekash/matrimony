<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\Controller;
use App\Event;

class News extends Controller
{
    //
   var $msg;

    public function __construct()
    {
        # code...
        $this->middleware('auth:admin');
       
    }
    public function index()
    {
       $news = Event::orderBy('id','desc')->get();
        
       return view('admin.news',compact('news'));
    }

    public function addNews(Request $request)
    {

     $validator = Validator::make($request->all(), [
                   'title'=>'required|min:3',
                   'description'=>'required|min:30',
                   "image" => 'image|mimes:jpg,jpeg,png|max:2048'
     ]);
          
        if ($validator->fails()) {
            return response()->json(array('errors' => $validator->errors()), 422);
        }
       $fullname = "";
     if ($request->hasFile('image')) {
        $image = $request->file('image');
        $name = time().'.'.$image->getClientOriginalExtension();
        
        $destinationPath = 'assets/images/news/';
        if($image->move($destinationPath, $name))
        $fullname = "/assets/images/news/$name";
    }

     $data = [
         'image' => $fullname,
         'heading' => $request->title,
         'description' => $request->description
     ];

        if(Event::create($data)){
             
            

            return ["success"=>true, "message"=>"News Added Successfully" ];
        }
       
       return ["success"=>false, "message"=>"Unable to add news" ];
        
    }

public function deleteNews($id = 0)
{
    $event = Event::find($id);
    if(Event::destroy($id)){
        $image = ltrim($event->image,"/");
        if(file_exists($image)) unlink($image);
        return redirect('admin/news')->with('news-msg',"News Successfully Deleted");

       }
     
     else {

        return redirect('admin/news')->with('news-msg',"Unable to Deleted News");
     }
}

}

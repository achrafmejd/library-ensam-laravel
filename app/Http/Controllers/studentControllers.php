<?php

namespace App\Http\Controllers;

use App\Models\Copie;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Models\Favorit;

use Illuminate\Support\Facades\Auth;

use Intervention\Image\Facades\Image;
use Intervention\Image\Facades\Photos;


class studentControllers extends Controller
{
    //


    function home() {
        $pageTitle = 'home';
        return view('student.welcome', compact('pageTitle'));
    }

    function books(){
        $pageTitle = 'Vitrine des livres';
        $etudiant=1;
        $i=0;
        $j=0;
        $nblikes = DB::table('favorits')
                    ->select('book_id',DB::raw("COUNT(*) as nb"))
                    ->groupBy('book_id');

        $data = DB::table('books')
                    ->leftJoinSub($nblikes, 'n', function ($join)
                    {
                        $join->on('n.book_id', '=', 'books.id');
                    })
                    ->get();
        $fav=DB::table('favorits')
                    ->select('book_id')
                    ->where('etudiant_id','=',$etudiant)
                    ->get();
        $copy = DB::table('copies')
                    ->where('state','=','disponible')
                    ->get();
        

        return view('student.books', compact('pageTitle','data','fav','i','j','copy'));
    }

    function about(){
        $pageTitle = 'A propos';
        return view('student.about', compact('pageTitle'));
    }

    function signin(){
        $pageTitle = "S'identifier";
        return view('student.signin', compact('pageTitle'));
    }

    function signup(){
        $pageTitle = 'Creer votre compte';
        return view('student.signup', compact('pageTitle'));
    }
    function team(){
        $pageTitle = 'Webmasters';
        return view('student.team', compact('pageTitle'));
    }

    private function resizeImage($contents) : string {
        return (string) Image::make($contents)->resize(75, 75)->encode('data-url');  // Very important this cast to String, without it you can not save correctly the binary in your DB;   
    }
    
    function addBook(Request $request){
        
        $title = $request->title;
        $author = $request->author;
        $edition= $request->edition;
        $isbn=$request->ISBN;
        $id_categorie=$request->id_categorie;
        $date_edition=$request->date_edition;
        $file = file_get_contents($request->book_image);
        //$file = file_get_contents($request->book_image);
        $description = $request->description;
       
        //$blob = base64_encode($request->book_image);
        // $url = json_decode($request->book_image); //Photo is the field that I fill in the view, that contain a URL to my image in pixabay;
        // $contents = file_get_contents($url);
        // $name = substr($url, strrpos($url, '/') + 1);
        // $blob =  base64_encode($this->resizeImage($contents));

        /* $content = $file->openFile()->fread($file->getSize());

        $image = $request->file( 'book_image' );
            $imageType = $image->getClientOriginalExtension();
            $imageStr = (string) Image::make( $image )->
                                     resize( 300, null, function ( $constraint ) {
                                         $constraint->aspectRatio();
                                     })->encode( $imageType ); */
    
        //return $content;
        DB::insert('insert into books (title, author, edition, date_edition, ISBN, book_image, description, id_categorie) values (?, ?, ?, ?, ?, ?, ?, ?)', [$title, $author, $edition, $date_edition, $isbn, $file, $description, $id_categorie]);

        return 'true';
    }

  
    
   
  


    /* Add this in postman with the key value _token */
    public function showToken() {
        echo csrf_token(); 
      }

    public function getProduct($id){
        $file = $this->fileRepo->find($id);

        $randomDir = md5(time() . $file->id . $file->user->id . str_random());

        mkdir(public_path() . '/files/' . $randomDir);

        $path = public_path() . '/files/' . $randomDir . '/' . $file->name;

        file_put_contents($path, base64_decode($file->data));

        header('Content-Description: File Transfer');

        return response()->download($path);
    }
    public function like($id){
        $etudiant=1;
        $data=NULL;
        $data=DB::table('favorits')
            ->where('etudiant_id','=',$etudiant)
            ->where('book_id','=',$id)
            ->get();
        if($data->isEmpty())
        {
            DB::insert('insert into favorits (etudiant_id,book_id) values (?, ?)', [$etudiant, $id]);
        }
        else
        {
            foreach($data as $b)
            {
                $fav=Favorit::find($b->id);
                $fav->delete();
            }
        }
        return redirect()->back();
    }

    public function book($Id){
        /* We fetch the book from the DB 
            Send the State of the book to the user 
                if dispo => send dispo & number
                if not dispo => send it and date to be dispo
        */
        
        $disponible = 0;
        $reservee = 0;
        $perdu = 0;
        /* This is for getting the book from db by id */
        $book = DB::table('books')->find($Id);
        /* This is concerning the tables copy ot get all states of the book specified by id
        and count it in order to get the number of copies of the book */ 
        $book_state = DB::table('copies')->where('book_id', '=', $Id)->get();
        //return $book_state;
        $numberOfCopies = $book_state->count();
        /* This is a join between the tables copy & reservation
        why ? => We need the get the nearest date when the book will be available 
        We need to check all the copies reserverd and get their dates 
        */ 
        $reservations = DB::table('copies')
                           ->join('reservations', 'copies.id', '=', 'reservations.copy_id')
                           ->where('book_id', '=', $Id)
                            ->get();
        
         /* The function below just calculate the state of each book */
        foreach($book_state as $key) {
            if($key->state == "disponible"){
                $disponible++;
            }else if ($key->state == "reserve"){
                $reservee++;
            }else{
                $perdu++;
            }
        }
   
        /* Here We add 7 days to the oldest date of the reservation => oldest + 7jr is the nearest date now */
        if(count($reservations)>0){
            $nearestDateToBeDisponible = date('Y-m-d', strtotime($reservations->sortBy('date_reservation')->first()->date_reservation. '+ 7 days'));
        }else{
            $nearestDateToBeDisponible = date('Y-m-d');
        }
        
        /* We look for all comments of the book */

        $userAndComment = DB::table('etudiants')
                    ->join('comments', 'etudiants.id', '=', 'comments.user_id')
                    ->select('nom', 'prenom', 'comment', 'date_comment')
                    ->where('book_id', '=',$Id)
                    ->get();

        $nbComments = $userAndComment->count();
  
        /* Summary */ 
        $summary = ["numberOfCopies" => $numberOfCopies, "disponible"=> $disponible,"reservee" => $nearestDateToBeDisponible, "perdu" => $perdu, "nbComments"=>$nbComments ,"comments"=> json_decode($userAndComment, true)];

        //return $nearestDateToBeDisponible;
        return view('student.singleBook', compact('book' , 'summary'));
        //return $userAndComment;
    }

    
    public function reserver(Request $request){
        $out = new \Symfony\Component\Console\Output\ConsoleOutput();
        $out->writeln($request);
        /* we go to copies tables and get one copy of the book which is disponible to reserve it */
        $book_copy = DB::table('copies')
            ->select('id')
            ->where('book_id', '=',$request->idBook)
            ->where('state', '=', 'disponible')
            ->first();

        if($book_copy){
            DB::insert('insert into reservations (date_reservation, etudiant_id, copy_id) values (?,?,?)', [$request->dateToSend, $request->userId, $book_copy->id]);
            DB::table('copies')
                ->where('id', '=',$book_copy->id)
                ->update(['state'=>'reserve']);
                
            return response("Success", 200);
            //return $book_copy;
        }else{
            return response("Error", 404);
        }
        //return response("Error", 400);
        //return $book_copy;
    }

    public function sendComment(Request $request){
        $out = new \Symfony\Component\Console\Output\ConsoleOutput();
        $out->writeln($request->get('comment'));


        DB::insert('insert into comments (comment, book_id, user_id, date_comment) values (?,?,?,?)', [$request->comment, $request->idBook,$request->userId, $request->dateToSend]);
        return response("Sucess", 200);
        //return $request;
    }

    public function studentInfos(Request $request){
        //return Image::make($request->book_name);
        /* $upload=$request->book_image->store('public/uploads/');
        return ["result"=>$upload]; */
        error_log($request->cne);
        $apogee = $request->apogee;
        $cne = $request->cne;
        $cin = $request->cin;
        $nom= $request->nom;
        $prenom=$request->prenom;
        $date_de_naissance=$request->date_de_naissance;
        $adresse=$request->adresse;
        $email_institutionnel=$request->email_institutionnel;
        $email_personnel=$request->email_personnel;
        $sexe=$request->sexe;
        $user_id= Auth::user()->id;


        //return $content;
        DB::insert('insert into etudiants (apogee, cne, cin, nom, prenom, date_de_naissance, adresse,email_institutionnel,email_personnel,sexe,user_id) values (?, ?, ?, ?, ?, ?, ?,?,?,?,?)', [$apogee, $cne, $cin, $nom, $prenom, $date_de_naissance, $adresse,$email_institutionnel,$email_personnel,$sexe,$user_id]);

        return redirect("/");
    }

    public function profile(){
        $pageTitle="Profile";
        return view("student.profile",compact("pageTitle"));
    }

}

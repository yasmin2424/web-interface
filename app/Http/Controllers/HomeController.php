<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PatientsModel;
use App\Models\HealthOfficersGeneral;
use App\Models\RegisterDonorMoney;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Redirect;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }
    protected  $general_officer_id;
    protected  $referal_officer_id;

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */

    //   public totalPatientsNumber(){

    //   }
    protected function generalHospitalOfficers(){
        //modifications
        $dbresult = DB::table('health_officers_generals')
        ->join('patients_details_generals', 'health_officers_generals.id', '=',
         'patients_details_generals.officer_id')
        ->select('health_officers_generals.officer_name','health_officers_generals.id',
         'health_officers_generals.hospital_name',
 
        DB::raw('COUNT(patients_details_generals.officer_id) as total_patients_number')
        )
        ->groupBy('health_officers_generals.officer_name', 'health_officers_generals.id',
         'health_officers_generals.hospital_name')
       ->get();
        //end modifs
        return $dbresult;
    }
    protected function referralHospitalOfficers(){
        return DB::table('health_officers_referals')
        ->join('patients_details_referals', 'health_officers_referals.id', '=',
         'patients_details_referals.officer_id')
        ->select('health_officers_referals.officer_name','health_officers_referals.id',
         'health_officers_referals.hospital_name',
  
        DB::raw('COUNT(patients_details_referals.officer_id) as total_patients_number')
        )
        ->groupBy('health_officers_referals.officer_name', 'health_officers_referals.id',
         'health_officers_referals.hospital_name')
       ->get();
    }
    protected function nationalHospitalOfficers(){
        return 
        $officers_national =DB::table('health_officers_nationals')
        ->join('patients_details_nationals', 'health_officers_nationals.id', '=',
         'patients_details_nationals.officer_id')
        ->select('health_officers_nationals.officer_name','health_officers_nationals.id',
         'health_officers_nationals.hospital_name',
   
        DB::raw('COUNT(patients_details_nationals.officer_id) as total_patients_number')
        )
        ->groupBy('health_officers_nationals.officer_name', 'health_officers_nationals.id',
         'health_officers_nationals.hospital_name')
       ->get();
   
    }
    protected function checkGeneralTreatedPatients($officer_array){
          $treated_patients =  array_filter($officer_array,function($officers){
            if($officers->total_patients_number > 5){
                $this->general_officer_id = $officers->id;
                return $officers;
            }
           }
        );
        if(count($treated_patients)){
            $officer_total = DB::table('regional_hospitals')->min('officer_total');
            $hospital_details = DB::table('regional_hospitals')->where('officer_total', $officer_total)->first();
            $officer_details = DB::table('health_officers_generals')->where('id', '=', $this->general_officer_id)->get();
            //Deactivate the officer from the general table
            $officerToDeactivate = HealthOfficersGeneral::find($this->general_officer_id);
            
            $officerToDeactivate->active = 0;
            $officerToDeactivate->save();
            // //Delete officer from health_officers_generals table
             DB::delete('delete from health_officers_generals where id =?',[$this->general_officer_id]);

           //insert record
           DB::table('health_officers_referals')->insert([
            'officer_name' =>$officer_details[0]->officer_name ,
            'role'=>'senior officer',
            'hospital_id'=>$hospital_details->id,
            'user_id'=>1,
            'hospital_name'=>$hospital_details->hospital_name
        ]);

        //increment the regional hospitals

        return 
        DB::table('regional_hospitals')->where('officer_total', '=', $officer_total)->increment('officer_total', 1);



        }
        else{
            return ;
        }
    }
    protected  function checkReferralHospital($officer_array){
        $treated_patients =  array_filter($officer_array,function($officers){
            if($officers->total_patients_number > 5){
                $this->referal_officer_id = $officers->id;
                return $officers;
            }
           }
        );
        if(count($treated_patients)){
        return 
        DB::table('health_officers_referals')
              ->where('id', $this->referal_officer_id)
              ->update([
                  'upgrade' => 'covid 19 consultant',
                   'award'=>'10000000',
                   'pending'=>True
              ]);



        }
        else{
            return ;
        }

    }
    protected function pendingOfficerList(){
        return DB::table('health_officers_referals')
        ->where('pending', True)
        ->get();
    }
   protected function format_currency($array_currency){
    return  array_map(function($currency){
        if($currency->award){
           $currency->award = number_format($currency->award, 2, '.', ',');
           $currency->pending = 'Yes';
           return $currency;
        }
        return $currency;
   }, $array_currency);
}
   
    public function index()
    {
      $officers_general =$this->generalHospitalOfficers();
      $officers_referral =  $this->referralHospitalOfficers();
      $officers_national = $this->nationalHospitalOfficers();
      $this->checkGeneralTreatedPatients($officers_general->toArray());
      $this->checkReferralHospital($officers_referral->toArray());
      $pendingList = $this->pendingOfficerList();
      $this->format_currency($pendingList->toArray());
       return view('home',
       [
        'officers_general'=>$officers_general,
        'officers_referral'=>$officers_referral,
        'officers_national'=>$officers_national,
        'officers_pending'=>$pendingList
       ]

    );
    }

    protected function formatCurrency($array){
        return array_map(function($element){
            return $element;
        }, $array);
    }
    //here
      
    public string $default_month;
    public  string $set_month;
    public $set =False;
    protected function  getFromStore(){
        if($this->set){
              $store_month = $this->set_month;
            $donor_money = DB::table('register_donor_money')
            ->select('amount')
            ->where('month', '=', $store_month)
            ->get();  
            $this->default_month = $store_month;
            
            return $donor_money[0]->amount;
      
    
         }
         else{
            $donor_money = DB::table('register_donor_money')
            ->select('amount')
            ->where('id', '=', 1)->
            get();
            $this->default_month = 'January';
            if (!$donor_money->count()) {
              return;
            }
            return $donor_money[0]->amount;
      
         }
    }
    
   
    private function calculateExcessAmount($first_amount, $second_amount){
        if((int)$first_amount > (int)$second_amount){
            $diff = (int)$first_amount-(int)$second_amount;
            return (int)$diff;
        }
        else return 'less amount';
    }
    private function  calculatePayments($first_amount, $second_amount){
        $remaining_amount = (int)$first_amount-(int)$second_amount;
        return (int)$remaining_amount;
    }
    //calculate officer total
    private function totalOfficers($array){
        return count($array);
    }
    // private function  formatCurrency($currency_array){
    //    return  array_map(function($currency){
    //          if($currency->monthly_allowane){
    //             $currency->monthly_allowane = number_format($currency->monthly_allowane, 2, '.', ',');
    //             return $currency;
    //          }
    //          else{
    //              $currency->monthly_allowane = number_format($currency->monthly_allowane,2);
    //              return $currency;
    //          }
    //     }, $array_currency);
    // }
    //here
    public function store(Request $request){
        $this->validate($request, 
        ['month'=>'required']
    );
    $this->set_month = $request->month;
    $this->set = True;
   //here


   $diff_money = $this->calculatePayments((int)$this->getFromStore(),(int)100000000 );
        $months = DB::table('register_donor_money')->select('month')
        ->where('amount', ">", 100000000)
        ->get();
      //echo $donor_money; 
      if($diff_money>0){
        $remaining_amount = $this->calculatePayments($diff_money, 5000000);
        $director_money_national_referal = 5000000;
        $superintendent_money = $director_money_national_referal/2;
        $remaining_after_superintendent = $this->calculatePayments($remaining_amount, $superintendent_money);
        $administrator_money = $superintendent_money*(3/4);
        $remaining_after_admin = $this->calculatePayments($remaining_after_superintendent, $administrator_money);

        //calcutte total officers in general hospitals
        $total_officers_general = $this->totalOfficers(DB::select('select role from health_officers_generals'));

        //officers general hospital salary
        $general_officer_salary = $administrator_money*(8/5);
        $total_officer_salary = $general_officer_salary*$total_officers_general;
        $remaining_after_general_officer_salary = $this->calculatePayments($remaining_after_admin, $total_officer_salary);

        //echo $remaining_after_general_officer_salary;

        //total senior officers
        $senior_officer_salary = $general_officer_salary + $general_officer_salary*(6/100);
        $total_senior_officers = $this->totalOfficers(DB::select(
            'select role from health_officers_referals where role = ?', ['senior officer']));
        $total_senior_officer_salary = $total_senior_officers*$senior_officer_salary;
    
        $remaining_amount_after_senior_officers = $this->
        calculatePayments($remaining_after_general_officer_salary, $total_senior_officers);

        //total officers money apart from general
        $all_officer_salary = $director_money_national_referal+$administrator_money
        + $superintendent_money + $senior_officer_salary;
        $bonus_general_officers = $all_officer_salary*(3.5/100);

        $general_officer_salary+=$all_officer_salary;
        //echo $general_officer_salary;
        $total_general_officer_salary =  $total_officers_general*$general_officer_salary;

        $remaining_after_general_officer_bonus = $this->
        calculatePayments($remaining_amount_after_senior_officers, $total_general_officer_salary);

        //echo $remaining_after_general_officer_bonus;

        //total money plus the bonus money

        $director_money_national_referal+=($remaining_after_general_officer_bonus*(5/100));
        $superintendent_total_salary = $director_money_national_referal/2;
        $admin_total_salary  = $superintendent_total_salary*(3/4);
        $officer_total_salary = $admin_total_salary*(8/5);
        $senior_total_salary = $officer_total_salary + $officer_total_salary*(6/100);
        $all_officer_salary = $director_money_national_referal + $superintendent_total_salary +$admin_total_salary
        +$senior_total_salary;
        $officer_total_general_salary = $officer_total_salary + $all_officer_salary*(3.5/100);

        $money = '200';

        //updating records
          DB::update("update health_officers_nationals set monthly_allowane = $director_money_national_referal
          where role = ?", ['director']);
          DB::update("update health_officers_referals set monthly_allowane = $superintendent_total_salary
          where role = ?", ['superintendent']);
          DB::update("update health_officers_referals set monthly_allowane = $senior_total_salary
          where role = ?", ['senior officer']);
          DB::update("update health_officers_generals set monthly_allowane = $officer_total_general_salary
          where role = ?", ['officer']);
          DB::update("update health_officers_generals set monthly_allowane = $officer_total_general_salary
          where role = ?", ['head']); 
          DB::update("update users set monthly_allowane = $director_money_national_referal
          where role = ?", ['director']);
          DB::update("update users set monthly_allowane = $admin_total_salary
          where role = ?", ['administrator']);  



        
          //return a view
          $staff_money = $this->formatCurrency(DB::select("select role, name, monthly_allowane from users"));
          $officers_at_general_hospitals = $this->formatCurrency(DB::select('select role, officer_name, monthly_allowane
           from health_officers_generals'));
           $officers_at_referal_hospitals = $this->formatCurrency(DB::select('select role, 
           officer_name, monthly_allowane
           from health_officers_referals'));
           $officers_at_national_hospitals = $this->formatCurrency(DB::select('select role, officer_name, 
           monthly_allowane
           from health_officers_nationals'));
           return view('moneydistributed',
           ['staff_payments'=>$staff_money,
           'officers_at_general'=>$officers_at_general_hospitals,
           'officers_at_referal'=>$officers_at_referal_hospitals,
           'officers_at_national'=>$officers_at_national_hospitals,
           'months'=>$months,
           'default'=>$this->default_month
           ]
        );
          

      }
      else {
          $staff_money = array();
          $officers_at_general_hospitals = array();
          $officers_at_referal_hospitals = array();
          $officers_at_national_hospitals = array();
          $months = array();
        return view('moneydistributed',
        ['staff_payments'=>$staff_money,
        'officers_at_general'=>$officers_at_general_hospitals,
        'officers_at_referal'=>$officers_at_referal_hospitals,
        'officers_at_national'=>$officers_at_national_hospitals,
        'months'=>$months,
        'default'=>$this->default_month
        ]);
      }
    
}

    //here

    public function checkPayments()
    {
       $diff_money = $this->calculatePayments((int)$this->getFromStore(),(int)100000000 );
        $months = DB::table('register_donor_money')->select('month')
        ->where('amount', ">", 100000000)
        ->get();
      if($diff_money >0){
        $remaining_amount = $this->calculatePayments($diff_money, 5000000);
        $director_money_national_referal = 5000000;
        $superintendent_money = $director_money_national_referal/2;
        $remaining_after_superintendent = $this->calculatePayments($remaining_amount, $superintendent_money);
        $administrator_money = $superintendent_money*(3/4);
        $remaining_after_admin = $this->calculatePayments($remaining_after_superintendent, $administrator_money);

        //calcutte total officers in general hospitals
        $total_officers_general = $this->totalOfficers(DB::select('select role from health_officers_generals'));

        //officers general hospital salary
        $general_officer_salary = $administrator_money*(8/5);
        $total_officer_salary = $general_officer_salary*$total_officers_general;
        $remaining_after_general_officer_salary = $this->calculatePayments($remaining_after_admin, $total_officer_salary);

        //echo $remaining_after_general_officer_salary;

        //total senior officers
        $senior_officer_salary = $general_officer_salary + $general_officer_salary*(6/100);
        $total_senior_officers = $this->totalOfficers(DB::select(
            'select role from health_officers_referals where role = ?', ['senior officer']));
        $total_senior_officer_salary = $total_senior_officers*$senior_officer_salary;
    
        $remaining_amount_after_senior_officers = $this->
        calculatePayments($remaining_after_general_officer_salary, $total_senior_officers);

        //total officers money apart from general
        $all_officer_salary = $director_money_national_referal+$administrator_money
        + $superintendent_money + $senior_officer_salary;
        $bonus_general_officers = $all_officer_salary*(3.5/100);

        $general_officer_salary+=$all_officer_salary;
        //echo $general_officer_salary;
        $total_general_officer_salary =  $total_officers_general*$general_officer_salary;

        $remaining_after_general_officer_bonus = $this->
        calculatePayments($remaining_amount_after_senior_officers, $total_general_officer_salary);

        //echo $remaining_after_general_officer_bonus;

        //total money plus the bonus money

        $director_money_national_referal+=($remaining_after_general_officer_bonus*(5/100));
        $superintendent_total_salary = $director_money_national_referal/2;
        $admin_total_salary  = $superintendent_total_salary*(3/4);
        $officer_total_salary = $admin_total_salary*(8/5);
        $senior_total_salary = $officer_total_salary + $officer_total_salary*(6/100);
        $all_officer_salary = $director_money_national_referal + $superintendent_total_salary +$admin_total_salary
        +$senior_total_salary;
        $officer_total_general_salary = $officer_total_salary + $all_officer_salary*(3.5/100);

        $money = '200';

        //updating records
          DB::update("update health_officers_nationals set monthly_allowane = $director_money_national_referal
          where role = ?", ['director']);
          DB::update("update health_officers_referals set monthly_allowane = $superintendent_total_salary
          where role = ?", ['superintendent']);
          DB::update("update health_officers_referals set monthly_allowane = $senior_total_salary
          where role = ?", ['senior officer']);
          DB::update("update health_officers_generals set monthly_allowane = $officer_total_general_salary
          where role = ?", ['officer']);
          DB::update("update health_officers_generals set monthly_allowane = $officer_total_general_salary
          where role = ?", ['head']); 
          DB::update("update users set monthly_allowane = $director_money_national_referal
          where role = ?", ['director']);
          DB::update("update users set monthly_allowane = $admin_total_salary
          where role = ?", ['administrator']);  



        
          //return a view
          $staff_money = $this->formatCurrency(DB::select("select role, name, monthly_allowane from users"));
          $officers_at_general_hospitals = $this->formatCurrency(DB::select('select role, officer_name, monthly_allowane
           from health_officers_generals'));
           $officers_at_referal_hospitals = $this->formatCurrency(DB::select('select role, 
           officer_name, monthly_allowane
           from health_officers_referals'));
           $officers_at_national_hospitals = $this->formatCurrency(DB::select('select role, officer_name, 
           monthly_allowane
           from health_officers_nationals'));
           return view('moneydistributed',
           ['staff_payments'=>$staff_money,
           'officers_at_general'=>$officers_at_general_hospitals,
           'officers_at_referal'=>$officers_at_referal_hospitals,
           'officers_at_national'=>$officers_at_national_hospitals,
           'months'=>$months,
           'default'=>$this->default_month
           ]
        );
          

      }
      else {
          $staff_money = array();
          $officers_at_general_hospitals = array();
          $officers_at_referal_hospitals = array();
          $officers_at_national_hospitals = array();
          $months = array();
        return view('moneydistributed',
        ['staff_payments'=>$staff_money,
        'officers_at_general'=>$officers_at_general_hospitals,
        'officers_at_referal'=>$officers_at_referal_hospitals,
        'officers_at_national'=>$officers_at_national_hospitals,
        'months'=>$months,
        'default'=>$this->default_month
        ]);
      }
      //$formated_currency;
        //electing stuff members
     
    }

    //here

    //public function checkPayments() {
      //  $funds = RegisterDonorMoney::whereMonth('created_at', Carbon::now()->month)->sum('amount');
        //$funds1 = RegisterDonorMoney::whereMonth('created_at', Carbon::now()->month)->get();
        
        //if($funds > 100000000){
          /*  $fundsToDistribute = $funds - 100000000;
            $director = User::find(3);
            $director->monthly_allowane = 5000000;
            $fundsToDistribute -= $director->monthly_allowane;
            $superintendent = User::find(4);
            $superintendent->monthly_allowane = 1/2 * ($director->monthly_allowane);
            $fundsToDistribute -= $superintendent->monthly_allowane;
            $administrator = User::find(5);
            $administrator->monthly_allowane = 3/4 * ($superintendent->monthly_allowane);
            $fundsToDistribute -= $administrator->monthly_allowane;
            $officers = HealthOfficersGeneral::get();
            foreach($officers as $officer) {
                $officer->monthly_allowane = 8/5 * ($administrator->monthly_allowane);
                $officer->save();
                $fundsToDistribute -= $officer->monthly_allowane;
            }
            $director->monthly_allowane += 5/100 * $fundsToDistribute;
            $superintendent->monthly_allowane += 1/2 * (5/100 * $fundsToDistribute);
            //save the allowances
            $director->save();
            $superintendent->save();
            $administrator->save();
            $months = DB::select("select month from register_donor_money");
            $default = $months[0]->month;

           // return Redirect::to("/home")->withSuccess('All staff have been paid.');
           $staff_money = $this->formatCurrency(DB::select("select role, name, monthly_allowane from users"));
          $officers_at_general_hospitals = $this->formatCurrency(DB::select('select role, officer_name, monthly_allowane
           from health_officers_generals'));
           $officers_at_referal_hospitals = $this->formatCurrency(DB::select('select role, 
           officer_name, monthly_allowane
           from health_officers_referals'));
           $officers_at_national_hospitals = $this->formatCurrency(DB::select('select role, officer_name, 
           monthly_allowane
           from health_officers_nationals'));
           return view('moneydistributed',
           ['staff_payments'=>$staff_money,
           'officers_at_general'=>$officers_at_general_hospitals,
           'officers_at_referal'=>$officers_at_referal_hospitals,
           'officers_at_national'=>$officers_at_national_hospitals,
           'months'=>$months,
           'default'=>$default
           ]
        );

        }
        return Redirect::to("/home")->withFail('Insufficient Funds.');
    }

    public function distributedFunds(){
    //     $staff_money = $this->formatCurrency(DB::select("select role, name, monthly_allowane from users"));
    //       $officers_at_general_hospitals = $this->formatCurrency(DB::select('select role, officer_name, monthly_allowane
    //        from health_officers_generals'));
    //        $officers_at_referal_hospitals = $this->formatCurrency(DB::select('select role, 
    //        officer_name, monthly_allowane
    //        from health_officers_referals'));
    //        $officers_at_national_hospitals = $this->formatCurrency(DB::select('select role, officer_name, 
    //        monthly_allowane
    //        from health_officers_nationals'));
    //        return view('moneydistributed',
    //        ['staff_payments'=>$staff_money,
    //        'officers_at_general'=>$officers_at_general_hospitals,
    //        'officers_at_referal'=>$officers_at_referal_hospitals,
    //        'officers_at_national'=>$officers_at_national_hospitals,
    //        'months'=>$months,
    //        'default'=>$this->default_month
    //        ]
    //     );
    }*/
}

<?php

namespace App\Http\Controllers;

use App\FailedTransaction;
use App\Http\Controllers\Controller;
use App\PaymentMethod;
use App\Plan;
use App\Subscription;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use phpDocumentor\Reflection\Types\This;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\UnknownApiErrorException;
use Stripe\InvoiceItem;
use Stripe\Stripe;
use Stripe\Token;

class SubscriptionController extends Controller
{
    public static $fromRegister = false;
    private $paymentMethod = null;
    public function __construct()
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
    }

    public function index(User $user, Request $request)
    {
        $user = auth()->user();
        $payments = PaymentMethod::whereUserId($user->id)->get();
        $plans = Plan::all();
        return view('subscription.create', [
            'plans' => $plans,
            'user' => $user,
            'payments' => $payments
        ]);
    }

    public function checkStripeCustomer($email)
    {
        $stripe = new \Stripe\StripeClient(
            env('STRIPE_SECRET')
        );
        $customers = $stripe->customers->all([
            'email' => $email,
        ]);
        $stripeCustomer = null;
        $plans = Plan::all();
        if ( isset($customers->data) && !empty($customers->data)){
            foreach ($customers->data as $customer) {
                if (isset($customer->subscriptions->data) && !empty($customer->subscriptions->data)){
                    foreach ($customer->subscriptions->data as $subscription) {
                        if (isset($subscription->items->data) && !empty($subscription->items->data)){
                            foreach ($subscription->items->data as $plan) {
                                if (isset($plan->plan->id)){
                                    foreach ($plans as $DBPlan) {
                                        $stripeCustomer['subscription'] = ($plan->plan->id == $DBPlan->stripe_plan) ? json_encode($subscription) : null;
                                        $stripeCustomer['customer'] = ($plan->plan->id == $DBPlan->stripe_plan) ? json_encode($customer) : null;
                                        if ($stripeCustomer != null){
                                            return $stripeCustomer;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    public function createCustomer($customerData = array())
    {
        $cardArray = array(
            "number" => $customerData['number'],
            "exp_month" => $customerData['exp_month'],
            "exp_year" => $customerData['exp_year'],
            "cvc" => $customerData['cvc'],
            "name" => $customerData['name']
        );
        try {
            $token = Token::create(["card" => $cardArray]);
            $response = Customer::create([
                "email" => $customerData['email'],
                "metadata" => "",
                "description" => $customerData['name'],
                "source" => $token
            ]);
        } catch (ApiConnectionException $e) {
            $response = [
                'success' => false,
                'message' => $e->getMessage() ?? 'Something went wrong! please try again later.'
            ];
            // Network problem, perhaps try again.
        } catch (InvalidRequestException $e) {
            $response = [
                'success' => false,
                'message' => $e->getMessage() ?? 'Something went wrong! please try again later.'
            ];
            // You screwed up in your programming. Shouldn't happen!
        } catch (CardException $e) {
            $response = [
                'success' => false,
                'message' => $e->getMessage() ?? 'Card declined. Invalid card information.'
            ];
            // Card was declined.
        }
        return $response;
    }

    /************************************************/


    public function cancel(Request $request)
    {
        $user = \auth()->user();
        return view('subscription.cancel',compact('user'));
    }

    public function transactions(Request $request)
    {
        $user = \auth()->user();
        $subscriptions = Subscription::whereUserId($user->id)->whereStatus(1)->get();
        return view('subscription.index',compact('subscriptions'));
    }

    //
    public function create(Request $request, User $user)
    {
        $user = auth()->user();
        $plan = Plan::whereId($request->plan)->first();
        $data = $request->toArray();

        if ($plan) {
            $response = $this->chargePayment($data, $user, $plan);
            return response()->json($response);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Please select plan.'
            ]);
        }
    }

    public function chargePayment($data,$user,$plan)
    {
        $this->paymentMethod = $data['payment_method'];
        $paymentMethod = PaymentMethod::whereId($data['payment_method'])->first();
        if (!isset($paymentMethod->id)){
            return [
                'success' => false,
                'message' => 'Payment method is not available. Please add payment method.'
            ];
        }
        $returnCustomer = json_decode(decrypt($paymentMethod->stripe_customer_object));
        if (isset($returnCustomer->id)) {
            $stripe = new \Stripe\StripeClient(
                env('STRIPE_SECRET')
            );
            $plan = Plan::first();
            try {
                $subscription = $stripe->subscriptions->create([
                    'customer' => $returnCustomer->id,
                    'items' => [
                        [
                            'price' => $plan->stripe_plan,
                            //'description' => $plan->name. ' New Subscription'
                        ],
                    ],
                ]);
            }catch (CardException $e){
                return [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }

            /*User::whereId($user->id)->update([
                'plan_id'=>$plan->id,
                'payment_method_id' => $checkPaymentMethod->id ?? $paymentMethod->id
            ]);*/
            /*$planStatus = $this->checkPlanStatus($user);
            if (!$planStatus['doCharge']){
                return  [
                    'success' => false,
                    'message' => "Your current subscription isn't ended yet."
                ];
            }
            $returnCharge = $this->makeCharge($returnCustomer, $plan);
            if ( isset($returnCharge['success']) && !$returnCharge['success']) {
                return $returnCharge;
            }*/
            if (isset($subscription->id)){
                User::whereId($user->id)->update([
                    'stripe_subscription_object' => json_encode($subscription),
                    'subscription_status' => 1,
                ]);
                self::sendMail("You have successfully subscribed to ".env('APP_NAME'));
                return ['success' => true, 'message' => "You have successfully subscribed."];
            }else{
                return ['success' => false, 'message' => "Something went wrong while creating subscription. Please try again later."];
            }
        }else{
            return ['success' => false, 'message' => "Please add payment method first then subscribe."];
        }
        //return $this->saveSubscriptionToDB($user, $plan, $returnCharge, $returnCustomer,$planStatus);
    }

    public function unsubscribe(Request $request)
    {
        $stripe = new \Stripe\StripeClient(
            env('STRIPE_SECRET')
        );
        $user = \auth()->user();

        $customer = $user->stripe_customer_object ?? null;
        if ($customer == null){
            $customer = $this->checkStripeCustomer($user->email);
        }

        if ($customer != null){
            $subscription = isset($user->stripe_subscription_object) ? json_decode($user->stripe_subscription_object) : $customer['subscription'];
            /**
             * 1 ===>>>  If the subscription is first time and under 7 days. Give monday back.
             * 2 ===>>>  if the subscription is second time and under 24 hours then give monday back.
             * 3 ===>>>  Rest of the cases will go to the till the last date of subscription.
             * */

                $invoices = $stripe->invoices->all([
                    'subscription' => $subscription->id
                ]);
            $invoice = null;
            $invoiceCount = isset($invoices->data) ? count($invoices->data) : null;
            if ( $invoiceCount <= 2){
                if (count($invoices->data) == 1){
                    $invoice = $invoices->first();
                }elseif (count($invoices->data) == 2){
                    $invoice = $invoices->last();
                }
                if ($invoice != null){
                    $relaxPeriod = $invoiceCount == 1 ? '+7 days' : '+1 day';
                    $firstRefundOption = strtotime($relaxPeriod.date('Y-m-d h:i:s A',$invoice->created));
                    $today = strtotime(date('Y-m-d h:i:s A'));
                    if ($firstRefundOption > $today){
                        // Give refund
                        //$invoice = $stripe->invoices->retrieve($subscription->latest_invoice);
                        try {
                            $response = $stripe->refunds->create([
                                'amount' => $subscription->plan->amount_decimal,
                                'charge' => $invoice->charge,
                            ]);
                        }catch (InvalidRequestException $e){

                        }

                        try {
                            $cancel = $stripe->subscriptions->cancel(
                                $subscription->id,
                            );
                        }catch (InvalidRequestException $e){}
                    }
                }
            }else{
                return response()->json([
                    'subscription_status' => null,
                    'success' => false,
                    'message' => 'Something went wrong. Please try again later.'
                ]);
            }

            if ($invoice == null){
                $stripe->subscriptions->update($subscription->id,[
                    'cancel_at_period_end' => true,
                ]);
            }
        }else{
            return response()->json([
                'subscription_status' => null,
                'success' => false,
                'message' => 'Customer not found. Please create subscription from clickfunnels' //Todo: need to change it
            ]);
        }

        //$subscription_status = isset($user->subscription_status) ? !$user->subscription_status : 0;
        User::whereId($user->id)->update([
            'subscription_status' => 0
        ]);
        self::sendMail("You have unsubscribed to ".env('APP_NAME').". If you want to get our services please subscribe again.");
        return response()->json([
            'subscription_status' => null,
            'success' => true,
            'message' => 'Subscription status has been updated.'
        ]);
    }

    public function upgradePlan(Request $request)
    {
        $plans = Plan::whereType(1)->get();
        $selectedPlan = Subscription::whereUserId(Auth::user()->id)->where('status', 1)->first();
        $user = \auth()->user();
        //$paymentMethod = PaymentMethod::whereUserId($user->id)->first();
        $paymentMethod = isset($user->payment_method_id) ? PaymentMethod::whereId($user->payment_method_id)->first() : null;
        $subscription = Subscription::whereUserId($user->id)->whereStatus(1)->first();
        $nextPlan = collect($plans)->where('id',$user->plan_id)->first();
        return view('payment.upgrade_plan.index', [
            'plans' => $plans,
            'selectedPlan' => $selectedPlan,
            'paymentMethod' => $paymentMethod,
            'subscription' => $subscription,
            'nextPlan' => $nextPlan
        ]);
    }

    public function checkPlanStatus($user)
    {
        $subscription = Subscription::whereUserId($user->id)->whereStatus(1)->first();
        $doCharge = true;
        if (isset($subscription->id)) {
            $now = Carbon::now()->toDate()->format('Y-m-d');
            $doCharge = (strtotime($subscription->ends_at) < strtotime($now)) ? true : false;
        }
        return [
            'doCharge' => $doCharge,
            'subscription' => $subscription,
        ];
    }

    public function makeCharge($customer, $plan)
    {
            $customerID = $customer->id;
            $invoiceItem1 = [
                "customerID" => $customerID,
                "amount" => $plan->cost,
                "description" => $plan->name
            ];
            $this->createInvoiceItem($invoiceItem1);
            // Charge Setup fee from customer
            $planFee = [
                'amount' => $plan->cost,
                'customer' => $customerID,
                'description' => $invoiceItem1['description'],
                'source' => $customer->default_source,
            ];
            return $this->createCharge($planFee);
    }

    public static function createCustomerOld($customerData = array())
    {
        $cardArray = array(
            "number" => $customerData['number'],
            "exp_month" => $customerData['exp_month'],
            "exp_year" => $customerData['exp_year'],
            "cvc" => $customerData['cvc'],
            "name" => $customerData['name'],
            "address_zip" => $customerData['address_zip'],
            "address_country" => $customerData['address_country'],
        );
        try {
            $token = Token::create(["card" => $cardArray]);
            $response = Customer::create([
                "email" => $customerData['email'],
                "metadata" => "",
                "description" => $customerData['name'],
                "source" => $token
            ]);
        } catch (ApiConnectionException $e) {
            $response = [
                'success' => false,
                'message' => $e->getMessage() ?? 'Something went wrong! please try again later.'
            ];
            // Network problem, perhaps try again.
        } catch (InvalidRequestException $e) {
            $response = [
                'success' => false,
                'message' => $e->getMessage() ?? 'Something went wrong! please try again later.'
            ];
            // You screwed up in your programming. Shouldn't happen!
        } catch (CardException $e) {
            $response = [
                'success' => false,
                'message' => $e->getMessage() ?? 'Card declined. Invalid card information.'
            ];
            // Card was declined.
        }
        return $response;
    }

    /*
     * Stripe Payment function used to create payment charge from customer with
     * valid credit card information
    */
    public function createCharge($stripeData = array())
    {
        try {
            $charge = Charge::create([
                'amount' => bcmul($stripeData['amount'], 100),
                'currency' => 'usd',
                'customer' => $stripeData['customer'],
                "description" => $stripeData['description'],
                'source' => $stripeData['source'],
            ]);
        } catch (CardException $e) {
            $charge = ['success' => false, 'message' => $e->getMessage()];
        }
        return $charge;
    }

    /*
     * Stripe Payment function used to create invoice line item
     */
    public function createInvoiceItem($invoiceData = array())
    {
        try {
            $lineItem = InvoiceItem::create([
                "customer" => $invoiceData['customerID'],
                'amount' => bcmul($invoiceData['amount'], 100),
                "currency" => "usd",
                "description" => $invoiceData['description']
            ]);

        } catch (Exception $e) {
            $lineItem = $e->getMessage();
        }
        return $lineItem;
    }

    public function saveSubscriptionToDB($user, $plan, $chargeObject, $customer, $planStatus = null)
    {
        $oldSubscription = $planStatus['subscription'] ?? null;
        if (!isset($planStatus['subscription'])){
            $checkDBSub = Subscription::whereUserId($user->id)->whereStatus(1)->first();
            if (isset($checkDBSub->id)){
                $oldSubscription = $checkDBSub;
            }else{
                $oldSubscription = null;
            }
        }
        $planEndsDate = Carbon::now()->addDays(30)->toDate()->format('Y-m-d');
        $subscription = [
            'user_id' => $user->id,
            'name' => $user->name,
            'stripe_id' => $customer->id ?? '',
            'stripe_status' => $chargeObject->captured ?? null,
            'stripe_plan' => $plan->stripe_plan ?? null,
            'plan_id' => $plan->id ?? 0,
            'payment_method' => $customer->default_source ?? null,
            'payment_method_id' => $this->paymentMethod,
            'status' => 1,
            'charge_object' => json_encode($chargeObject),
            'ends_at' => $planEndsDate,
            'transaction_id' => $chargeObject->balance_transaction ?? null,
            'charge_id' => $chargeObject->id ?? null,
            'amount_charged' => $plan->cost ?? 0
        ];
        Subscription::create($subscription);
        if (isset($oldSubscription->id)) {
            Subscription::whereId($oldSubscription->id)->update(['status' => 4]);
        }

        return ['success' => true, 'message' => "You have successfully subscribed."];
    }

    public function autoRenew($user, $currentSubscription, $plan = null)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $planStatus = self::checkPlanStatus($user);
        $paymentMethod = DB::table('users')
            ->where('users.id',$user->id)
            ->join('payment_methods as pm','users.payment_method_id','=','pm.id')
            ->first();
        if (!isset($paymentMethod->stripe_id) || !isset($paymentMethod->payment_method_id)){
            return false;
        }
        $customer = [
            'id' => decrypt($paymentMethod->stripe_id),
            'name' => $user->name,
            'default_source' => decrypt($paymentMethod->payment_method)
        ];
        $chargeObject = $this->makeCharge((object)$customer,$plan);
        if ( isset($chargeObject['success']) && !$chargeObject['success']) {
            return $chargeObject;
        }
        if (isset($chargeObject->captured)) {
            $plan = [
                'id' => $plan->id ?? $currentSubscription->plan_id,
                'stripe_plan' => $plan->stripe_plan ?? $currentSubscription->stripe_plan,
                'cost' => $plan->cost,
                'hits' => $plan->hits,
            ];
            $this->saveSubscriptionToDB($user, (object)$plan, $chargeObject, (object)$customer, $planStatus);
            return [
                'success' => true,
                'message' => 'Plan has been successfully renewed.'
            ];
        } else {
            return false;
        }
    }

    public function autoRenewal(Request $request)
    {
        //d-M-Y
        //Todo: need to add logic to get subscription data.
        $users = DB::table('users')->whereNotNull('payment_method_id')->where('subscription_status',1)->get();
        if (!empty($users)) {
            $ids = $users->pluck('id');
            $now = Carbon::now()->toDate()->format('Y-m-d');
            $subscriptions = DB::table('subscriptions')
                                ->whereIn('user_id',$ids)
                                ->where('status',1)
                                ->where('ends_at','<=',$now)
                                ->get();
            if (!empty($subscriptions)){
                $plans = Plan::all();
                foreach ($subscriptions as $subscription) {
                    $user = $users->where('id',$subscription->user_id)->first();
                    $plan = $plans->where('id',$subscription->plan_id)->first();
                    $response = $this->autoRenew($user, $subscription,$plan);
                    if (isset($response['success']) && !$response['success']){
                        FailedTransaction::create([
                            'user_id' => $user->id,
                            'payment_method_id' => $user->payment_method_id,
                            'message' => $response['message']
                        ]);
                    }
                }
            }

        }
    }

    public function createDummySubscription(Request $request)
    {
        $stripe = new \Stripe\StripeClient(
            env('STRIPE_SECRET')
        );
        /*$pm = $stripe->paymentMethods->retrieve('card_1HAJCHATKQllV66FdgAPjQ8j');
        echo '$pm';
        echo '<pre>';
        print_r($pm);
        echo '</pre>';
        exit();*/
        /*$stripe->customers->update('cus_Hjmloy9v8hRZVJ',[
            'default_source' => 'card_1HAJCHATKQllV66FdgAPjQ8j'
        ]);
        $customer = $stripe->customers->retrieve('cus_Hjmloy9v8hRZVJ');
        echo '$customer';
        echo '<pre>';
        print_r($customer);
        echo '</pre>';
        exit();

        $subscription = $stripe->subscriptions->retrieve('sub_HjmlZEKG9nFPj2');
        echo '$subscription';
        echo '<pre>';
        print_r($subscription);
        echo '</pre>';
        exit();
        $subscription->default_payment_method->updateAttributes('pm_1HAJn3ATKQllV66Fi7VVsG4l');
        echo '$subscription';
        echo '<pre>';
        print_r($subscription);
        echo '</pre>';
        exit();*/
        /*$pm = $stripe->paymentMethods->all([
            'customer' => 'cus_Hjmt66tV5Qlcub',
            'type' => 'card'
        ]);*/

        /*$stripe->paymentMethods->detach(
            'card_1HAJJQATKQllV66FG1bRaS5z',
            []
        );*/

        /*$stripe->paymentMethods->attach(
            'pm_1HAJn3ATKQllV66Fi7VVsG4l',
            ['customer' => 'cus_Hjmt66tV5Qlcub']
        );

        $pm = $stripe->paymentMethods->all([
            'customer' => 'cus_Hjmt66tV5Qlcub',
            'type' => 'card'
        ]);

        echo '$pm';
        echo '<pre>';
        print_r($pm);
        echo '</pre>';
        exit();*/
        $cardArray = [
            "number" => 4242424242424242,
            "exp_month" => 10,
            "exp_year" => 20,
            "cvc" => 123,
            "name" => 'Azeem',
            'email' => 'azeemshami34@gmail.com'
        ];
        $newCustomer = $this->createCustomer($cardArray);
        $customerID = $newCustomer->id;
        try {
            $subscription = $stripe->subscriptions->create([
                'customer' => $newCustomer->id,
                'items' => [
                    ['price' => 'price_1H9cvUATKQllV66Fl09K0Fd0'],
                ],
            ]);
        }catch (CardException $e){
            var_dump($e->getMessage());
            exit();
        }

        echo '$subscription';
        echo '<pre>';
        print_r($subscription);
        echo '</pre>';
    }

    public function dummyRefund(Request $request)
    {
        $stripe = new \Stripe\StripeClient(
            env('STRIPE_SECRET')
        );
        $invoice = $stripe->invoices->retrieve('in_1HCXq0ATKQllV66FPiCc0f0v');
        echo '$invoice';
        echo '<pre>';
        print_r($invoice);
        echo '</pre>';

        $response = $stripe->refunds->create([
            'amount' => 19700,
            'charge' => $invoice->charge,
        ]);
        /*$cancel = $stripe->subscriptions->cancel(
            'sub_Hm5WzCQpmaL3YM',
        );*/
        echo '$response';
        echo '<pre>';
        print_r($response);
        echo '</pre>';
        exit();
        /*$cancel = $stripe->subscriptions->cancel(
            'sub_Hm5WzCQpmaL3YM',
        );*/
        echo '$cancel';
        echo '<pre>';
        print_r($cancel);
        echo '</pre>';
        exit();
    }

    public static function sendMail($notice)
    {
        $user = \auth()->user();
        //$notice = "You have unsubscribed to ".env('APP_NAME').". If you want to get our services please subscribe again.";
        try {
            Mail::send('mails.notification', ['user' => $user,'notice'=>$notice], function ($m) use ($user) {
                $m->from(env('MAIL_FROM_ADDRESS'), env('APP_NAME'));
                $m->to($user->email, $user->name)->subject('TFC Notification!');
            });
        }catch (\Exception $e){
            dd($e->getMessage());
        }
    }

    public function testMail(Request $request)
    {
        self::sendMail('Hello world');
    }

}

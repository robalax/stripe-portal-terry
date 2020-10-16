<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SubscriptionController;
use App\PaymentMethod;
use App\Plan;
use App\Providers\RouteServiceProvider;
use App\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function create(array $data)
    {
        $subscriptionController = new SubscriptionController();
        $stripeCustomer = $subscriptionController->checkStripeCustomer($data['email']);
        $decodedCus = isset($stripeCustomer['customer']) ? json_decode($stripeCustomer['customer']) : null;
        $paymentMethod = isset($decodedCus->default_source) ? $decodedCus->default_source : null;
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'stripe_customer_object' => $stripeCustomer['customer'] ?? null,
            'stripe_subscription_object' => $stripeCustomer['subscription'] ?? null,
            'subscription_status' => isset($stripeCustomer['subscription']) ? 1 : null,
        ]);
        if ($paymentMethod != null){
            $stripe = new \Stripe\StripeClient(
                env('STRIPE_SECRET')
            );
            $paymentMethodObj = $stripe->paymentMethods->retrieve($paymentMethod);
            $fingerPrint = isset($paymentMethodObj->card->fingerprint) ? md5($paymentMethodObj->card->fingerprint) : null;
            $last4 = isset($paymentMethodObj->card->last4) ? encrypt($paymentMethodObj->card->last4) : null;
            //$checkPaymentMethod = $fingerPrint != null ? PaymentMethod::whereUserId($user->id)->whereCardFingerPrint($fingerPrint)->first() : null;
            //if (!isset($checkPaymentMethod->id)){
                $default = PaymentMethod::create([
                    'user_id' => $user->id,
                    'stripe_customer_object' => isset($stripeCustomer['customer']) ? encrypt($stripeCustomer['customer']) : null,
                    'stripe_id' => isset($decodedCus->id) ? encrypt($decodedCus->id) : null,
                    'payment_method' => isset($decodedCus->default_source) ? encrypt($decodedCus->default_source) : null,
                    'card_finger_print' => $fingerPrint,
                    'last4' => $last4
                ]);
                User::whereId($user->id)->update(['payment_method_id' => $default->id]);
            //}
        }
        return $user;
    }
}

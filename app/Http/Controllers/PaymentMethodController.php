<?php

namespace App\Http\Controllers;

use App\PaymentMethod;
use App\Plan;
use App\User;
use Illuminate\Http\Request;
use Stripe\Customer;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Stripe;
use Stripe\Token;

class PaymentMethodController extends Controller
{
    //
    private $card = null;
    public function index(Request $request)
    {
        $user = auth()->user();
        $cards = PaymentMethod::whereUserId($user->id)->get();
        return view('payment_method.index',[
            'cards' => $cards,
            'user' => $user
        ]);
    }

    public function create(Request $request)
    {
        return view('payment_method.add');
    }

    public function edit(Request $request)
    {
        $user = auth()->user();
        $method = isset($user->payment_method_id) ? PaymentMethod::whereId($user->payment_method_id) : null;
        if ((isset($user->payment_method_id) && $user->payment_method_id == null) || !isset($method->id) || $method->id == null){
            return redirect()->route('add-payment-method')->with('error','Please add payment method before updating it.');
        }
        return view('payment_method.edit',compact('method'));
    }


    public function store(Request $request)
    {
        $user = auth()->user();
        $data = $request->toArray();
        return $this->confirmPayment($data, $user);
    }

    /**
     * @param $paymentDetail
     * @param $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmPayment($paymentDetail, $user)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $email = $user->email;
        $card_number = $paymentDetail['card_number'] ?? '';
        $exp_month = isset($paymentDetail['exp_date']) ? explode('/', $paymentDetail['exp_date'])[0] : '';
        $exp_year = isset($paymentDetail['exp_date']) ? explode('/', $paymentDetail['exp_date'])[1] : '';
        $cvc = $paymentDetail['cvc'] ?? '';
        $name = $user->name;

        $customerData = array(
            'number' => $card_number,
            'email' => $email,
            'exp_month' => (int)$exp_month,
            'exp_year' => (int)$exp_year,
            'cvc' => $cvc,
            'name' => $name,
        );
        $returnCustomer = $this->createCustomer($customerData);
        if (isset($returnCustomer->id)) {
            /**
             * Already added card won't be added again
             * */
            $cards = $returnCustomer->sources->data;
            $stripeCard = null;
            foreach ($cards as $card) {
                if ($this->card == $card->id){
                    $stripeCard = $card;
                    continue;
                }
            }
            $fingerPrint = isset($stripeCard->fingerprint) ? md5($stripeCard->fingerprint) : null;
            $last4 = isset($stripeCard->last4) ? encrypt($stripeCard->last4) : null;
            $checkPaymentMethod = $fingerPrint != null ? PaymentMethod::whereUserId($user->id)->whereCardFingerPrint($fingerPrint)->first() : null;
            if (!isset($checkPaymentMethod->id)) {
                $paymentMethod = PaymentMethod::create([
                    'user_id' => $user->id,
                    'stripe_customer_object' => isset($returnCustomer) ? encrypt(json_encode($returnCustomer)) : null,
                    'stripe_id' => isset($returnCustomer->id) ? encrypt($returnCustomer->id) : null,
                    'payment_method' => isset($returnCustomer->id) ? encrypt($returnCustomer->id) : null,
                    'card_finger_print' => $fingerPrint,
                    'last4' => $last4
                ]);
                if ($paymentMethod->id) {
                    User::whereId($user->id)->update([
                        'payment_method_id' => $paymentMethod->id,
                    ]);
                    SubscriptionController::sendMail("You have successfully added a new payment method.");
                    return response()->json([
                        'success' => true,
                        'message' => "Payment method has been added."
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => "Failed to add payment method please try again later."
                    ]);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "You've already added this payment method."
                ]);
            }
        }
        return response()->json($returnCustomer);
    }

    public function createCustomer($customerData = array())
    {
        $user = auth()->user();
        $customer = (isset($user->stripe_customer_object)) ? json_decode($user->stripe_customer_object) : null;
        $cardArray = array(
            "number" => $customerData['number'],
            "exp_month" => $customerData['exp_month'],
            "exp_year" => $customerData['exp_year'],
            "cvc" => $customerData['cvc'],
            "name" => $customerData['name']
        );
        try {
            $token = Token::create(["card" => $cardArray]);
            $this->card = $token->card->id;
            if ($customer != null){
                $response = Customer::update($customer->id,[
                    'source' => $token
                ]);
            }else{
                $response = Customer::create([
                    "email" => $customerData['email'],
                    "metadata" => "",
                    "description" => $customerData['name'],
                    "source" => $token
                ]);
                User::whereId($user->id)->update(['stripe_customer_object' => $response]);
            }
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

    public function makeDefault(Request $request)
    {
        $stripe = new \Stripe\StripeClient(
            env('STRIPE_SECRET')
        );
        $cardId = $request->card_id;
        $user = auth()->user();
        if (isset($user->stripe_subscription_object)){
            $customer = json_decode($user->stripe_customer_object);
            $pm = PaymentMethod::whereId($cardId)->first();
            if (!empty($pm)){
                try {
                    $newCustomer = $stripe->customers->update($customer->id,[
                        'default_source' => decrypt($pm->payment_method)
                    ]);
                }catch (InvalidRequestException $e){
                    return [
                        'success' => false,
                        'message' => 'The payment method you are trying to add is invalid!'
                    ];
                }
            }
        }
        User::whereId(auth()->user()->id)->update([
            'payment_method_id' => $cardId,
            'stripe_customer_object' => json_encode($newCustomer)
        ]);
        SubscriptionController::sendMail("You have successfully updated your payment method. Next time payment will be charged from this card.");
        return response()->json([
            'success' => true,
            'message' => 'Card has been set as default.'
        ]);
    }

    public function deleteIt(Request $request)
    {
        $cardId = $request->card_id;
        PaymentMethod::whereId($cardId)->delete();
        return response()->json([
            'success' => true,
            'message' => 'Card has been deleted!'
        ]);
    }

}

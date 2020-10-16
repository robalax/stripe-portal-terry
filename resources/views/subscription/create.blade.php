@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card ">
                <div class="card-header card-header-success card-header-icon">
                    <div class="card-icon">
                        <i class="material-icons">add_shopping_cart</i>
                    </div>
                    <h4 class="card-title">Create Subscription</h4>
                </div>
                <div class="card-body ">
                    <div class="row">
                        <div class="col-sm-8 offset-2">
                            @if(!isset(auth()->user()->stripe_subscription_object) || auth()->user()->stripe_subscription_object == null || auth()->user()->subscription_status == 0)
                            <form method="post" action="javascript:;" id="subscribe-form">
                                @csrf
                                <div class="form-group">

                                    <select class="selectpicker form-control" data-size="7" data-style="btn" id="plan" name="plan" title="Select Plan">
                                        @foreach($plans as $key => $plan)
                                            <option value="{{$plan->id}}" {{$key == 0 ? 'selected' : ''}}>{{$plan->name.' ($'.$plan->cost.' every month)'}}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group">

                                    <select class="selectpicker form-control" data-size="7" data-style="btn" id="payment_method" name="payment_method" title="Select payment method">
                                    @if(count($payments))
                                            @foreach($payments as $key => $payment)
                                                <option value="{{$payment->id}}" {{$user->payment_method_id == $payment->id ? 'selected' : ''}}>{{'**** **** **** '.decrypt($payment->last4)}}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="form-group">
                                    <p>By click on this subscribe button you're agreed to the <a href="https://terryconsulting.clickfunnels.com/terms3rzrxz34" target="_blank">Terms of Agreement</a>.</p>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-fill btn-rose" id="subscribe_btn" onclick="createSubscription()">Subscribe</button>
                                </div>
                            </form>

                            @else
                            <h4 class="text-center text-success">You're already subscribed to {{env('APP_NAME')}}'s services.</h4>
                            @endif

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        function createSubscription() {
            let plan = $('#plan').val();
            let payment_method = $('#payment_method').val();
            if(plan === 'null' || plan == null){
                notice('Please select plan.','danger');
                return false;
            }
            if(payment_method === 'null' || payment_method == null){
                notice("Please select payment card or add new card if you didn't add it yet.",'danger');
                return false;
            }

            alertify.confirm('Are you sure you want to proceed this payment?',function () {

                preloader('#subscribe_btn',null);
                var formData = $('#subscribe-form').serialize();
                $.ajax({
                    url: "{{route('create-subscription')}}",
                    type: "POST",
                    data: formData,
                    success: function (data) {
                        preloader('#subscribe_btn','Subscribe');
                        if (data.success){
                            notice(data.message,'success')
                            location.reload();
                        }else{
                            notice(data.message,'danger')
                        }
                    },error:function (error) {
                        preloader('#subscribe_btn','Subscribe');
                        notice('Something went wrong!','danger')
                    }
                });

            },function () {
                console.log('Hi there');
            });
        }
    </script>
    @endpush

@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card ">
                <div class="card-header card-header-danger card-header-icon">
                    <div class="card-icon">
                        <i class="material-icons">cancel</i>
                    </div>
                    <h4 class="card-title">Cancel Subscription</h4>
                </div>
                <div class="card-body ">
                    <div class="row">
                        <div class="col-sm-12">
                            <h4 class="text-center text-danger">You are going to cancel subscription from {{env('APP_NAME')}}.</h4>
                            <form action="javascript:;" id="cancel-form" method="post">
                                @csrf
                                <p>
                                    Once confirmed, you will lose access to BAG at the END of the next billing cycle. <br>
                                    That means if you cancel on the 15th but your billing cycle doesn’t end until the 31st – you still get access to everything you paid for until the 31st. You won’t be rebilled after that.<br>

                                    <strong>NOTE:</strong> Per the agreed upon terms – refunds will ONLY be granted if you decide to cancel within 7 days of your initial purchase. Thanks for your cooperation.
                                    {{--If you want to unsubscribe or resubscribe to {{env('APP_NAME')}} then please click on the below button. If you cancel out your subscription then you will no longer able to get services of {{env('APP_NAME')}}.--}}
                                    <br>
                                </p>
                            <p class="text-center">
                                @if($user->subscription_status == 1)
                                    <button
                                        type="submit"
                                        id="cancel-btn"
                                        class="btn btn-danger"
                                        onclick="cancelSubscription()"
                                    > <i id="cancel-btn-icon" class="fa fa-times-circle"></i> <span id="cancel-btn-text">Cancel Subscription</span></button>
                                @elseif( $user->subscription_status === 0)
                                    <h4 class="text-danger  text-center" >You have unsubscribed to {{env('APP_NAME')}}</h4>
                                    @else
                                    <h4 class="text-info  text-center" >You haven't subscribed to {{env('APP_NAME')}} yet.</h4>
                                    @endif
                            </p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        function cancelSubscription(){
            alertify.confirm('Are you sure you want to cancel subscription?',function () {
                preloader('#cancel-btn',null);
                var formData = $('#cancel-form').serialize();
                $.ajax({
                    url: "{{route('unsubscribe')}}",
                    type: "POST",
                    data: formData,
                    success: function (data) {
                        //preloader('#cancel-btn','Cancel Subscription');
                        /*if (data.subscription_status){
                            console.log('fuck');
                            $('#cancel-btn-icon').removeClass('fa-times-circle').addClass('fa-check-circle');
                            $('#cancel-btn-text').text('Subscribe Again');
                            $('#cancel-btn').removeClass('btn-danger').addClass('btn-success');
                        }else{
                            console.log('lun');
                            $('#cancel-btn-icon').removeClass('fa-check-circle').addClass('fa-times-circle');
                            $('#cancel-btn-text').text('Cancel Subscription');
                            $('#cancel-btn').removeClass('btn-success').addClass('btn-danger');
                        }*/
                        notice(data.message,'success');
                        location.reload();
                    },error:function (error) {
                        preloader('#cancel-btn','Cancel Subscription');
                        notice('Something went wrong!','danger')
                    }
                });

            },function () {
            });
        }
    </script>
    @endpush


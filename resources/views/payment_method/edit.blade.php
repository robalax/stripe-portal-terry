@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card ">
                <div class="card-header card-header-success card-header-icon">
                    <div class="card-icon">
                        <i class="material-icons">payment</i>
                    </div>
                    <h4 class="card-title">Update Payment Method</h4>
                </div>
                <div class="card-body ">
                    <div class="row">
                        <div class="col-sm-8 offset-2">
                            <form action="javascript:;" method="post" id="payment-form">
                                @csrf
                                <div class="row">
                                    <div class="col-xl-12 col-lg-12 col-md-12 col-xs-12 col-sm-12">
                                        <div class="form-group">
                                            {{--<i class="fa fa-user-circle-o"></i>--{{asset('assets/frontend/images/Credit-Card-Icons.png')}}}}
                                            {{--<label>Credit Card Number <span class="required">*</span></label>--}}
                                            <div class="input-group">
                                                <input id="card_number" type="text" placeholder="Card Number" class="form-control @error('card_number') is-invalid @enderror" name="card_number" required value="{{ $method-> }}" autocomplete="card_number">
                                            </div>
                                            <span class="" role="alert" id="card_error"></span>
                                            @error('card_number')
                                            <span class="text-danger" role="alert">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-xl-6 col-lg-6 col-md-6 col-xs-12 col-sm-6">
                                        <div class="form-group">
                                            {{--<label>Expiry Date(mm/yy) <span class="required">*</span></label>--}}
                                            <input id="exp_date" type="text" placeholder="MM/YY" class="form-control @error('exp_date') is-invalid @enderror" name="exp_date" required value="{{ old('exp_date') }}" autocomplete="exp_date">
                                            @error('expiry_date')
                                            <span class="text-danger" role="alert">{{ $message }}</span>
                                            @enderror
                                            <span class="" role="alert" id="exp_error"></span>
                                        </div>
                                    </div>
                                    <div class="col-xl-6 col-lg-6 col-md-6 col-xs-12 col-sm-6">
                                        <div class="form-group">
                                            <div class="input-group">
                                                {{--<label>CVC <span class="required">*</span></label>--}}
                                                <input id="cvc" type="text" placeholder="CVC" class="form-control @error('cvc') is-invalid @enderror" name="cvc" required value="{{ old('cvc') }}" autocomplete="cvc">
                                            </div>
                                            @error('cvc')
                                            <span class="text-danger" role="alert">{{ $message }}</span>
                                            @enderror
                                            <span class="" role="alert" id="cvc_error"></span>
                                        </div>
                                    </div>
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <p>By click on this Add Card button you're agreed to the <a href="https://terryconsulting.clickfunnels.com/terms3rzrxz34" target="_blank">Terms of Agreement</a>.</p>
                                        </div>
                                    </div>
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-primary" id="add-btn">Add Card</button>
                                        </div>
                                    </div>

                                </div>
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
        $('#exp_date').payment('formatCardExpiry');
        $('#cvc').payment('formatCardCVC');
        $('#card_number').payment('formatCardNumber');

        function notice(message, cssClass){
            $.notify({
                // options
                message: message
            },{
                // settings
                type: cssClass,
                placement: {
                    from: "bottom",
                    align: "center"
                },
            });
        }

        var form = $('#payment-form');
        form.submit(function (e) {
            e.preventDefault();
            preloader('#add-btn',null);
            if (!$.payment.validateCardNumber($('#card_number').val())) {
                notice('Invalid card number!','danger');
                return false;
            }

            var exp = $.payment.cardExpiryVal($('#exp_date').val());;
            if (exp != null){
                if (!$.payment.validateCardExpiry(exp.month,exp.year)){
                    notice('Invalid expiry date!','danger');
                    return false;
                }
            }else{
                notice('Invalid expiry date!','danger');
                return false;
            }
            if (!$.payment.validateCardCVC($('#cvc').val())) {
                notice('Invalid CVC number!','danger');
                return false;
            }
            sendRequestToServer()
        });

        function spaceCheck(value,id,name) {
            if (value.trim() == ''){
                //Spaces check
                $(id).addClass('text-danger').text(`This field is required`);
                return false;
            }else{
                $(id).removeClass('text-danger').text('');
                return true;
            }
        }

        //if( $('#country').val() != undefined && $('#country').val() == 'US'){
        $("input#zip").on({
            keydown: function(e) {
                if ((e.which === 32))
                    return false;
            },
            change: function() {
                this.value = this.value.replace(/\s/g, "");
            }
        });
        //}

        $('#country').change(function(){
            let country = $('#country option:selected').val();
            countryWiseZip(country);

        })

        function countryWiseZip(country) {
            let zip = $('#zip');
            zip.val('');
            if(country == 'US'){
                zip.attr('maxlength',5).addClass('onlyNumber')
            }else if(country == 'CA'){
                zip.attr('maxlength',6).removeClass('onlyNumber')
            }
        }

        $("#zip").on("keypress keyup blur paste", function(event) {
            var that = this;
            let reg = /[^a-zA-Z0-9]+/g;
            if ($(this).hasClass('onlyNumber')) {
                if (event.which < 48 || event.which > 57) {
                    event.preventDefault();
                }
                reg = /[^0-9]+/g;
            }
            //paste event
            if (event.type === "paste") {
                setTimeout(function() {
                    $(that).val($(that).val().replace(reg, ""));
                }, 100);
            } else {
                $(this).val($(this).val().replace(reg, ""));
            }

        });

        function sendRequestToServer() {
            var formData = $('#payment-form').serialize();
            $.ajax({
                url: "{{route('payment_method.store')}}",
                type: "POST",
                data: formData,
                success: function (data) {
                    preloader('#add-btn','Add Card');
                    console.log(data);
                    let className = 'danger';
                    if (data.success){
                        document.getElementById('payment-form').reset();
                        className = 'success'
                    }
                    notice(data.message,className)
                },error:function (error) {
                    preloader('#add-btn','Add Card');
                    notice('Something went wrong!','danger')
                }
            });
        }

        function preloader(id,msg) {
            let loader = 'Please wait...';
            if (msg == null){
                console.log('in here');
                $(id).prop('disabled',true).text(loader);
            }else{
                console.log(msg);
                $(id).prop('disabled',false).text(msg);
            }
        }

    </script>

@endpush

@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card ">
                <div class="card-header card-header-success card-header-icon">
                    <div class="card-icon">
                        <i class="material-icons">list</i>
                    </div>
                    <h4 class="card-title">Available Payment Methods</h4>
                </div>
                <div class="card-body ">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="material-datatables">
                                <table id="datatables" class="table table-striped table-no-bordered table-hover text-center" cellspacing="0" width="100%" style="width:100%">
                                    <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Card</th>
                                        <th>State</th>
                                        <th>Created At</th>
                                        <th class="disabled-sorting">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(count($cards))
                                    @foreach($cards as $card)
                                        @php
                                        $cardObject = json_decode(decrypt($card->stripe_customer_object))
                                        @endphp
                                    <tr>
                                        <td>{{ $cardObject->description }}</td>
                                        <td>{{ $cardObject->email }}</td>
                                        <td>{{ isset($card->last4) ? '**** **** **** '.decrypt($card->last4) : null }}</td>
                                        <td>@php echo ($user->payment_method_id == $card->id) ? '<span class="text-success">Default</span>' : '<span class="text-info">Available</span>' @endphp</td>
                                        <td>{{$card->created_at}}</td>
                                        <td class="text-right">
                                            <form action="javascript:;" method="post" id="card-action-form">
                                                @csrf
                                                <button type="submit" class="btn btn-success " {{$user->payment_method_id == $card->id ? 'disabled' : ''}} id="default-btn" title="Make it as default" onclick="makeDefault('{{$card->id}}')"><i class="material-icons">check_circle</i> Make Default</button>
                                                {{--<button type="submit" class="btn btn-danger " {{$user->payment_method_id == $card->id ? 'disabled' : ''}} id="delete-btn" onclick="deletePaymentMethod('{{$user->payment_method_id == $card->id}}','{{$card->id}}')"><i class="material-icons">close</i> Delete</button>--}}
                                            </form>
                                        </td>
                                    </tr>
                                        @endforeach
                                        @else
                                        <tr>
                                            <td colspan="6">No Payment Method Available.</td>
                                        </tr>
                                    @endif

                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@push('scripts')
    <script>
        function makeDefault(card_id){
            alertify.confirm('Are you sure you want to make this payment method as default?',function () {

                preloader('#default-btn',null);
                var formData = $('#card-action-form').serializeArray();
                formData.push({
                    card_id: card_id
                });
                $.ajax({
                    url: "{{url('default-payment-method')}}/"+card_id,
                    type: "POST",
                    data: formData,
                    success: function (data) {
                        preloader('#default-btn','Make Default');

                        notice(data.message,'success');
                        //location.reload();
                    },error:function (error) {
                        preloader('#default-btn','Make Default');
                        notice('Something went wrong!','danger')
                    }
                });

            },function () {
                console.log('Hi there');
            });
        }

        function deletePaymentMethod(isDefault,card_id){
            if(isDefault){
                notice('This card is set as default please set any other card as default before deleting it.','danger')
                return false;
            }
            alertify.confirm('Are you sure you want to delete this payment method?',function () {
                preloader('#delete-btn',null);
                var formData = $('#card-action-form').serialize();
                $.ajax({
                    url: "{{url('delete-payment-method')}}/"+card_id,
                    type: "POST",
                    data: formData,
                    success: function (data) {
                        preloader('#delete-btn','Delete');
                        notice(data.message,'success');
                        location.reload();
                    },error:function (error) {
                        preloader('#delete-btn','Delete');
                        notice('Something went wrong!','danger')
                    }
                });

            },function () {
                console.log('Hi there');
            });
        }
    </script>
    @endpush

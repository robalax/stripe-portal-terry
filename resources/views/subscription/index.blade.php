@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card ">
                <div class="card-header card-header-success card-header-icon">
                    <div class="card-icon">
                        <i class="material-icons">list</i>
                    </div>
                    <h4 class="card-title">Active Subscriptions</h4>
                </div>
                <div class="card-body ">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="material-datatables">
                                <table id="datatables" class="table table-striped table-no-bordered table-hover text-center" cellspacing="0" width="100%" style="width:100%">
                                    <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Plan Name</th>
                                        <th>Card</th>
                                        <th>Amount</th>
                                        <th>Renew at</th>
                                        <th>Processed</th>
                                        <th>Receipt</th>
                                        <th>Created at</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(count($subscriptions))
                                        @foreach($subscriptions as $subscription)

                                            @php
                                                $charge_object = json_decode($subscription->charge_object);
                                            @endphp

                                            <tr>
                                                <td>{{ $subscription->name }}</td>
                                                <td>{{ $charge_object->description }}</td>
                                                <td>{{ isset($charge_object->payment_method_details->card->last4) ? '**** **** **** '.$charge_object->payment_method_details->card->last4 : '' }}</td>
                                                <td>{{ '$'.$charge_object->amount/100 }}</td>
                                                <td>{{ \Carbon\Carbon::parse($subscription->ends_at)->format('d-M-Y') }}</td>
                                                <td>
                                                    @if($charge_object->captured)
                                                        <i class="material-icons text-success">check_circle</i>
                                                        @else
                                                        <i class="material-icons text-danger">cancel</i>
                                                        @endif
                                                </td>
                                                <td><a href="{{$charge_object->receipt_url}}" target="_blank">View</a></td>
                                                <td>{{$subscription->created_at}}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="8">No Subscription Available.</td>
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

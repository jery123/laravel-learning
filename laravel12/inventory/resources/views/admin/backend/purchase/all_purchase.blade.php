@extends('admin.admin_master')
@section('content')

<div class="content">

                    <!-- Start Content-->
                    <div class="container-xxl">

                        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
                            <div class="flex-grow-1">
                                <h4 class="fs-18 fw-semibold m-0">All Purchases</h4>
                            </div>

                            <div class="text-end">
                                <ol class="breadcrumb m-0 py-0">
                                    <a href="{{ route('add.purchase') }}" class="btn btn-primary">Add Purchase</a>
                                </ol>
                            </div>
                        </div>

                        <!-- Datatables  -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">

                                    <div class="card-header">
                                        <h5 class="card-title mb-0">All Products</h5>
                                    </div><!-- end card header -->

                                    <div class="card-body">
                                        <table id="datatable" class="table table-bordered dt-responsive table-responsive nowrap">
                                            <thead>
                                            <tr>
                                                <th>S.I</th>
                                                <th>WareHouse</th>
                                                <th>Status</th>
                                                <th>Grand Total</th>
                                                <th>Payement</th>
                                                <th>Created At</th>
                                                <th>Action</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($purchases as $key => $purchase)
                                                    <tr>
                                                        <td>{{ $key + 1 }}</td>
                                                        <td>{{ $purchase->warehouse->name ?? 'N/A' }}</td>
                                                        <td>{{ $purchase->status }}</td>
                                                        <td>$ {{ $purchase->grand_total }}</td>
                                                        <td>Cash</td>
                                                        <td>{{ \Carbon\Carbon::parse($purchase->created_at)->format('Y-m-d') }}</td>
                                                        <td>
                                                            <a href="{{ route('details.purchase', $purchase->id) }}" class="btn btn-info sm" title="Details">
                                                                <span class="mdi mdi-eye-circle mdi-18px"></span>
                                                            </a>
                                                            <a href="{{ route('invoice.purchase', $purchase->id) }}" class="btn btn-primary sm" title="Details">
                                                                <span class="mdi mdi-download-circle mdi-18px"></span>
                                                            </a>
                                                            <a href="{{ route('edit.purchase', $purchase->id) }}" class="btn btn-success sm" title="Edit">
                                                                <span class="mdi mdi-book-edit mdi-18px"></span>
                                                            </a>
                                                            <a href="{{ route('delete.purchase', $purchase->id) }}" class="btn btn-danger sm" title="Delete" id="delete">
                                                                <span class="mdi mdi-delete-circle mdi-18px"></span>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach

                                            </tbody>
                                        </table>
                                    </div>

                                </div>
                            </div>
                        </div>


                    </div> <!-- container-fluid -->

                </div> <!-- content -->


@endsection

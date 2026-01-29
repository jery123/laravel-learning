@extends('admin.admin_master')
@section('content')

<div class="content">

                    <!-- Start Content-->
                    <div class="container-xxl">

                        <div class="py-3 d-flex align-items-sm-center flex-sm-row flex-column">
                            <div class="flex-grow-1">
                                <h4 class="fs-18 fw-semibold m-0">All Return Sales</h4>
                            </div>

                            <div class="text-end">
                                <ol class="breadcrumb m-0 py-0">
                                    <a href="{{ route('add.sale.return') }}" class="btn btn-primary">Add Return Sale</a>
                                </ol>
                            </div>
                        </div>

                        <!-- Datatables  -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">

                                    <div class="card-header">
                                        <h5 class="card-title mb-0">All Return Sales</h5>
                                    </div><!-- end card header -->

                                    <div class="card-body">
                                        <table id="datatable" class="table table-bordered dt-responsive table-responsive nowrap">
                                            <thead>
                                            <tr>
                                                <th>S.I</th>
                                                <th>WareHouse</th>
                                                <th>Status</th>
                                                <th>Grand Total</th>
                                                <th>Paid Amount</th>
                                                <th>Due Amount</th>
                                                <th>Created</th>
                                                <th>Action</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($sales as $key => $sale)
                                                    <tr>
                                                        <td>{{ $key + 1 }}</td>
                                                        <td>{{ $sale->warehouse->name ?? 'N/A' }}</td>
                                                        <td>{{ $sale->status }}</td>
                                                        <td>$ {{ $sale->grand_total }}</td>
                                                        <td>
                                                            <h4><span class="badge text-bg-info">$ {{ $sale->paid_amount }}</span></h4>
                                                        </td>
                                                        <td>
                                                            <h4><span class="badge text-bg-secondary">$ {{ $sale->due_amount }}</span></h4>
                                                        </td>
                                                        <td>{{ \Carbon\Carbon::parse($sale->created_at)->format('Y-m-d') }}</td>
                                                        <td>
                                                            <a href="{{ route('details.sale', $sale->id) }}" class="btn btn-info sm" title="Details">
                                                                <span class="mdi mdi-eye-circle mdi-18px"></span>
                                                            </a>
                                                            <a href="{{ route('invoice.sale', $sale->id) }}" class="btn btn-primary sm" title="Details">
                                                                <span class="mdi mdi-download-circle mdi-18px"></span>
                                                            </a>
                                                            <a href="{{ route('edit.sale', $sale->id) }}" class="btn btn-success sm" title="Edit">
                                                                <span class="mdi mdi-book-edit mdi-18px"></span>
                                                            </a>
                                                            <a href="{{ route('delete.sale', $sale->id) }}" class="btn btn-danger sm" title="Delete" id="delete">
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

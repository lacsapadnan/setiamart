<div class="modal fade" tabindex="-1" id="kt_modal_1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Detail Pembelian</h3>

                <!--begin::Close-->
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal"
                    aria-label="Close">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
                <!--end::Close-->
            </div>

            <div class="modal-body">
                <div id="kt_datatable_detail_wrapper dt-bootstrap4 no-footer" class="datatables_wrapper">
                    <div class="table-responsive">
                        <table class="table align-middle border table-row-dashed fs-6 g-5 dataTable no-footer"
                            id="kt_datatable_detail">
                            <thead>
                                <tr class="text-start fw-bold text-uppercase">
                                    <th>Produk</th>
                                    <th>Unit</th>
                                    <th>Quantity</th>
                                    <th>Harga Unit</th>
                                    <th>Subtotal</th>
                                    <th>Diskon Fix</th>
                                    <th>Diskon Persen</th>
                                    <th>Total Harga</th>
                                </tr>
                            </thead>
                            <tbody class="fw-semibold">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Returned Products Section -->
            <div class="mt-10">
                <h3 class="mb-5">Daftar Produk yang Diretur</h3>
                <div class="table-responsive">
                    <table class="table align-middle border table-row-dashed fs-6 g-5 dataTable no-footer"
                        id="kt_datatable_retur">
                        <thead>
                            <tr class="text-start fw-bold text-uppercase">
                                <th>No. Retur</th>
                                <th>Produk</th>
                                <th>Unit</th>
                                <th>Quantity</th>
                                <th>Harga</th>
                                <th>Total</th>
                                <th>Tanggal Retur</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
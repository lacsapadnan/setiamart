{{-- Barcode Print Modal --}}
<div class="modal fade" id="barcode_print_modal" tabindex="-1" aria-labelledby="barcodePrintModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="barcodePrintModalLabel">Cetak Barcode Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-4">
                    <i class="ki-duotone ki-information fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Pilih produk yang ingin dicetak barcodenya dan tentukan jumlah label untuk setiap produk.
                </div>
                <div class="container-fluid p-0 mb-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-md-auto">
                            <button type="button" class="btn btn-light-primary btn-sm" id="selectAllProductsBtn">Pilih Semua Produk</button>
                        </div>
                        <div class="col-12 col-md-auto">
                            <div class="input-group input-group-sm" style="max-width: 320px;">
                                <span class="input-group-text">Jumlah Label</span>
                                <input type="number" class="form-control" id="globalLabelQty" min="1" max="100" value="1">
                                <button class="btn btn-primary" type="button" id="applyGlobalQtyBtn">Terapkan</button>
                            </div>
                        </div>
                    </div>
                </div>
                <form id="barcodePrintForm">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle table-row-dashed mb-0" id="barcodeProductTable">
                            <thead>
                                <tr class="fw-bold fs-6 text-gray-800 bg-light">
                                    <th class="text-center" style="width: 50px;">
                                        <input type="checkbox" class="form-check-input" id="selectAllBarcodes">
                                    </th>
                                    <th>Nama Produk</th>
                                    <th>Harga Jual</th>
                                    <th class="text-center" style="width: 150px;">Jumlah Label</th>
                                </tr>
                            </thead>
                            <tbody id="barcodeProductList">
                                <!-- Will be populated by DataTables -->
                            </tbody>
                        </table>
                    </div>
                    <div id="noProductsMessage" class="alert alert-warning" style="display: none;">
                        <i class="ki-duotone ki-information-5 fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Tidak ada produk yang tersedia untuk dicetak.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="generateBarcodeBtn">
                    <i class="ki-duotone ki-printer fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                    </i>
                    Cetak Barcode
                </button>
            </div>
        </div>
    </div>
</div>


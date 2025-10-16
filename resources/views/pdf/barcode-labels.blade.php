<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Labels</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            background: white;
        }
        
        .labels-container {
            width: 100%;
            padding: 10px;
        }
        
        .label-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        
        .label-row {
            display: table-row;
            page-break-inside: avoid;
        }
        
        .label-cell {
            display: table-cell;
            width: 33.33%;
            padding: 8px;
            vertical-align: top;
        }
        
        .label {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            background: white;
            min-height: 130px;
        }
        
        .barcode-container {
            margin: 6px auto 4px auto;
            text-align: center;
            height: 62px;
            line-height: 62px;
            overflow: visible;
        }
        
        .barcode-container svg {
            height: 62px;
            width: auto;
            max-width: 100%;
        }
        
        .barcode-image {
            margin: 4px 0 2px 0;
            max-width: 100%;
            height: 60px;
            object-fit: contain;
        }
        
        .product-name {
            font-size: 9px;
            font-weight: bold;
            margin: 5px 0 3px 0;
            line-height: 1.2;
            max-height: 24px;
            overflow: hidden;
        }
        
        .barcode-number {
            font-size: 8px;
            color: #666;
            margin-top: 2px;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .label {
                break-inside: avoid;
            }
            
            @page {
                margin: 10mm;
                size: A4 portrait;
            }
        }
    </style>
</head>
<body>
    <div class="labels-container">
        <div class="label-grid">
            @foreach(array_chunk($products, 3) as $rowProducts)
            <div class="label-row">
                @foreach($rowProducts as $product)
                <div class="label-cell">
                    <div class="label">
                        <div class="product-name">{{ $product['name'] }}</div>
                        <div class="barcode-container">
                            @if(isset($product['barcode_svg']))
                                {!! $product['barcode_svg'] !!}
                            @elseif(isset($product['barcode_image']))
                                <img src="data:image/png;base64,{{ $product['barcode_image'] }}" 
                                     alt="Barcode" 
                                     class="barcode-image">
                            @endif
                        </div>
                        <div class="barcode-number">{{ $product['name'] }}</div>
                    </div>
                </div>
                @endforeach
                @if(count($rowProducts) < 3)
                    @for($i = count($rowProducts); $i < 3; $i++)
                    <div class="label-cell">
                        <!-- Empty cell for alignment -->
                    </div>
                    @endfor
                @endif
            </div>
            @endforeach
        </div>
    </div>
</body>
</html>


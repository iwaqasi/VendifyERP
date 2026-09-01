// ignore: avoid_web_libraries_in_flutter
import 'dart:html' as html;
import 'package:shared_preferences/shared_preferences.dart';

/// Paper size presets for thermal printers
enum PaperSize {
  /// Standard A4/Letter for regular printers
  regular,

  /// 80mm thermal receipt paper (most common POS thermal printer)
  thermal80,

  /// 58mm thermal receipt paper (compact POS thermal printer)
  thermal58,
}

class PrintService {
  static const String _paperSizeKey = 'print_paper_size';
  static const String _autoPrintKey = 'print_auto_print';
  static const String _copiesKey = 'print_copies';

  /// Get saved paper size preference
  Future<PaperSize> getPaperSize() async {
    final prefs = await SharedPreferences.getInstance();
    final index = prefs.getInt(_paperSizeKey) ?? 1; // Default to thermal80
    return PaperSize.values[index.clamp(0, PaperSize.values.length - 1)];
  }

  /// Save paper size preference
  Future<void> setPaperSize(PaperSize size) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_paperSizeKey, size.index);
  }

  /// Get auto-print setting (print immediately after sale)
  Future<bool> getAutoPrint() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(_autoPrintKey) ?? false;
  }

  /// Save auto-print setting
  Future<void> setAutoPrint(bool enabled) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_autoPrintKey, enabled);
  }

  /// Get number of copies to print
  Future<int> getCopies() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getInt(_copiesKey) ?? 1;
  }

  /// Save number of copies
  Future<void> setCopies(int copies) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_copiesKey, copies);
  }

  /// Print a receipt by opening a new window with formatted HTML and triggering print dialog
  ///
  /// [htmlContent] is the full HTML string to print
  /// [paperSize] determines the CSS @page size
  Future<void> printHtml(String htmlContent, {PaperSize? paperSize}) async {
    paperSize ??= await getPaperSize();
    final copies = await getCopies();

    // Build the complete HTML document with print-specific CSS
    final fullHtml = _buildPrintDocument(htmlContent, paperSize, copies);

    // Use JavaScript to open window and write content
    // This avoids dart:html type issues with WindowBase vs Window
    _openPrintWindow(fullHtml);
  }

  /// Use JS interop to open a print window - works across dart:html versions
  void _openPrintWindow(String htmlContent) {
    // Escape the HTML content for JS string literal
    final escaped = htmlContent
        .replaceAll('\\', '\\\\')
        .replaceAll("'", "\\'")
        .replaceAll('\n', '\\n')
        .replaceAll('\r', '\\r');

    // Use dart:js_util to call window.open and write content
    final jsCode = """
      (function() {
        var w = window.open('', '_blank');
        if (!w) { alert('Please allow popups for printing'); return; }
        w.document.open();
        w.document.write('$escaped');
        w.document.close();
        setTimeout(function() { w.print(); }, 500);
      })();
    """;

    // Execute via dart:js_util
    _executeJs(jsCode);
  }

  void _executeJs(String code) {
    // ignore: avoid_dynamic_calls
    final script = html.ScriptElement()..text = code;
    html.document.head!.append(script);
    script.remove();
  }

  /// Build a receipt HTML string from receipt data
  String buildReceiptHtml({
    required String businessName,
    required String invoiceNumber,
    required String invoicePrefix,
    required String dateTime,
    required String customerName,
    required List<Map<String, dynamic>> items,
    required String subtotal,
    String? tax,
    String? discount,
    required String grandTotal,
    required List<Map<String, dynamic>> payments,
    String? change,
    required String footer,
    String currencySymbol = 'KD',
  }) {
    final buffer = StringBuffer();

    buffer.writeln('''
<div class="receipt">
  <div class="header">
    <div class="business-name">$businessName</div>
    <div class="subtitle">Receipt</div>
  </div>
  <div class="invoice-no">$invoicePrefix$invoiceNumber</div>
  <div class="meta">$dateTime</div>
  <div class="meta">Customer: $customerName</div>
  <hr/>
  <table class="items">
    <thead>
      <tr>
        <th class="col-item">Item</th>
        <th class="col-qty">Qty</th>
        <th class="col-price">Price</th>
        <th class="col-total">Total</th>
      </tr>
    </thead>
    <tbody>''');

    for (final item in items) {
      buffer.writeln('''
      <tr>
        <td>${item['name']}</td>
        <td class="center">${item['quantity']}</td>
        <td class="right">$currencySymbol ${item['unit_price']}</td>
        <td class="right bold">$currencySymbol ${item['line_total']}</td>
      </tr>''');

      if (item['discount'] != null && item['discount'] != '0.000') {
        buffer.writeln('''
      <tr class="discount-row">
        <td>&nbsp; Discount</td>
        <td></td>
        <td></td>
        <td class="right">- $currencySymbol ${item['discount']}</td>
      </tr>''');
      }
    }

    buffer.writeln('''
    </tbody>
  </table>
  <hr/>
  <div class="totals">
    <div class="total-row"><span>Subtotal</span><span>$currencySymbol $subtotal</span></div>''');

    if (tax != null && tax != '0.000') {
      buffer.writeln('    <div class="total-row"><span>Tax</span><span>$currencySymbol $tax</span></div>');
    }
    if (discount != null && discount != '0.000') {
      buffer.writeln('    <div class="total-row negative"><span>Discount</span><span>- $currencySymbol $discount</span></div>');
    }

    buffer.writeln('''
    <hr class="thick"/>
    <div class="total-row grand-total"><span>TOTAL</span><span>$currencySymbol $grandTotal</span></div>
  </div>
  <div class="payment-box">
    <div class="payment-title">Payment</div>''');

    for (final p in payments) {
      buffer.writeln('    <div class="payment-row"><span>${p['method']}</span><span>$currencySymbol ${p['amount']}</span></div>');
    }

    if (change != null && change != '0.000') {
      buffer.writeln('    <div class="payment-row"><span>Change</span><span class="bold">$currencySymbol $change</span></div>');
    }

    buffer.writeln('''
  </div>
  <hr/>
  <div class="footer">$footer</div>
  <div class="barcode">
    <img src="https://barcodeapi.org/api/code128/$invoiceNumber" alt="barcode" />
    <div class="barcode-text">$invoiceNumber</div>
  </div>
</div>''');

    return buffer.toString();
  }

  /// Build complete HTML document with print CSS
  String _buildPrintDocument(String receiptHtml, PaperSize paperSize, int copies) {
    // Paper width CSS based on paper size
    String pageCss;
    switch (paperSize) {
      case PaperSize.thermal80:
        pageCss = '''
          @page { size: 80mm auto; margin: 2mm 4mm; }
          .receipt { width: 72mm; font-family: "Courier New", Courier, monospace; font-size: 11px; }
          .business-name { font-size: 14px; font-weight: bold; }
          .items { font-size: 10px; }
          .col-item { width: 40%; }
          .col-qty { width: 12%; }
          .col-price { width: 22%; }
          .col-total { width: 26%; }
        ''';
        break;
      case PaperSize.thermal58:
        pageCss = '''
          @page { size: 58mm auto; margin: 1mm 3mm; }
          .receipt { width: 52mm; font-family: "Courier New", Courier, monospace; font-size: 9px; }
          .business-name { font-size: 12px; font-weight: bold; }
          .items { font-size: 8px; }
          .col-item { width: 38%; }
          .col-qty { width: 12%; }
          .col-price { width: 22%; }
          .col-total { width: 28%; }
        ''';
        break;
      case PaperSize.regular:
        pageCss = '''
          @page { size: A4; margin: 15mm; }
          .receipt { width: 100%; max-width: 80mm; margin: 0 auto; font-family: "Courier New", Courier, monospace; font-size: 12px; }
          .business-name { font-size: 20px; font-weight: bold; }
          .items { font-size: 11px; }
          .col-item { width: 40%; }
          .col-qty { width: 12%; }
          .col-price { width: 22%; }
          .col-total { width: 26%; }
        ''';
        break;
    }

    return '''
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Receipt</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { background: #f5f5f5; }
    
    $pageCss
    
    .receipt {
      background: white;
      padding: 12px;
      margin: 0 auto;
    }
    
    /* Screen preview styles */
    @media screen {
      body { display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; padding: 20px; }
      .receipt { box-shadow: 0 2px 8px rgba(0,0,0,0.15); border-radius: 4px; max-width: 400px; }
    }
    
    /* Hide on screen, show on print */
    @media print {
      body { background: white; padding: 0; }
      .no-print { display: none !important; }
    }
    
    .header { text-align: center; margin-bottom: 8px; }
    .business-name { text-transform: uppercase; letter-spacing: 1px; }
    .subtitle { font-size: 10px; color: #666; margin-top: 2px; }
    .invoice-no { text-align: center; font-weight: bold; background: #f0f0f0; padding: 3px 8px; margin: 6px 0; display: inline-block; width: 100%; letter-spacing: 1px; }
    .meta { text-align: center; font-size: 10px; color: #666; }
    
    hr { border: none; border-top: 1px dashed #ccc; margin: 8px 0; }
    hr.thick { border-top: 2px solid #000; margin: 6px 0; }
    
    .items { width: 100%; border-collapse: collapse; margin: 6px 0; }
    .items th { text-align: left; font-weight: bold; padding: 3px 0; border-bottom: 1px solid #000; font-size: 10px; }
    .items td { padding: 3px 0; vertical-align: top; }
    .center { text-align: center; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
    .discount-row td { color: #c00; font-size: 9px; }
    
    .totals { margin: 4px 0; }
    .total-row { display: flex; justify-content: space-between; padding: 2px 0; font-size: 11px; }
    .total-row.negative { color: #c00; }
    .grand-total { font-size: 15px; font-weight: bold; padding: 4px 0; }
    
    .payment-box { background: #f9f9f9; padding: 6px 8px; border-radius: 3px; margin: 8px 0; }
    .payment-title { font-weight: bold; font-size: 10px; margin-bottom: 4px; }
    .payment-row { display: flex; justify-content: space-between; font-size: 11px; padding: 1px 0; }
    
    .footer { text-align: center; font-style: italic; font-size: 10px; color: #666; margin: 8px 0; }
    .barcode { text-align: center; margin-top: 8px; }
    .barcode img { max-width: 180px; height: auto; }
    .barcode-text { font-size: 8px; color: #999; letter-spacing: 1px; margin-top: 2px; }
  </style>
</head>
<body>
  $receiptHtml
  <script>
    // Auto-close after printing (optional)
    window.onafterprint = function() {
      // window.close(); // uncomment to auto-close
    };
  </script>
</body>
</html>''';
  }
}

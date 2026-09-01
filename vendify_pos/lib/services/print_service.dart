// ignore: avoid_web_libraries_in_flutter
import 'dart:html' as html;
import 'package:flutter/foundation.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
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

/// Cross-platform print service that works on Windows, Android, iOS, macOS, Linux, and Web.
///
/// - Web: opens browser print dialog with styled HTML receipt
/// - Desktop/Mobile: generates a PDF receipt and sends to system printer via `printing` package
class PrintService {
  static const String _paperSizeKey = 'print_paper_size';
  static const String _autoPrintKey = 'print_auto_print';
  static const String _copiesKey = 'print_copies';

  // ============ Settings ============

  Future<PaperSize> getPaperSize() async {
    final prefs = await SharedPreferences.getInstance();
    final index = prefs.getInt(_paperSizeKey) ?? 1;
    return PaperSize.values[index.clamp(0, PaperSize.values.length - 1)];
  }

  Future<void> setPaperSize(PaperSize size) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_paperSizeKey, size.index);
  }

  Future<bool> getAutoPrint() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(_autoPrintKey) ?? false;
  }

  Future<void> setAutoPrint(bool enabled) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_autoPrintKey, enabled);
  }

  Future<int> getCopies() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getInt(_copiesKey) ?? 1;
  }

  Future<void> setCopies(int copies) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_copiesKey, copies);
  }

  // ============ Print Entry Point ============

  /// Print a receipt — platform-aware.
  /// On web: opens browser print dialog with HTML receipt.
  /// On desktop/mobile: generates PDF and opens system print dialog.
  Future<void> printReceipt({
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
  }) async {
    final paperSize = await getPaperSize();

    if (kIsWeb) {
      // Web: use HTML + browser print dialog
      final htmlContent = buildReceiptHtml(
        businessName: businessName,
        invoiceNumber: invoiceNumber,
        invoicePrefix: invoicePrefix,
        dateTime: dateTime,
        customerName: customerName,
        items: items,
        subtotal: subtotal,
        tax: tax,
        discount: discount,
        grandTotal: grandTotal,
        payments: payments,
        change: change,
        footer: footer,
        currencySymbol: currencySymbol,
      );
      await _printHtmlWeb(htmlContent, paperSize);
    } else {
      // Desktop/Mobile: generate PDF and use system print dialog
      final pdfBytes = await buildReceiptPdf(
        businessName: businessName,
        invoiceNumber: invoiceNumber,
        invoicePrefix: invoicePrefix,
        dateTime: dateTime,
        customerName: customerName,
        items: items,
        subtotal: subtotal,
        tax: tax,
        discount: discount,
        grandTotal: grandTotal,
        payments: payments,
        change: change,
        footer: footer,
        currencySymbol: currencySymbol,
        paperSize: paperSize,
      );
      await _printPdfNative(pdfBytes);
    }
  }

  // ============ Web Printing (dart:html) ============

  Future<void> _printHtmlWeb(String htmlContent, PaperSize paperSize) async {
    final fullHtml = _buildPrintDocument(htmlContent, paperSize);

    // Inject script into current page to open print window
    final escaped = fullHtml
        .replaceAll('\\', '\\\\')
        .replaceAll("'", "\\'")
        .replaceAll('\n', '\\n')
        .replaceAll('\r', '\\r');

    final jsCode = '''
      (function() {
        var w = window.open('', '_blank');
        if (!w) { alert('Please allow popups for printing'); return; }
        w.document.open();
        w.document.write('$escaped');
        w.document.close();
        setTimeout(function() { w.print(); }, 500);
      })();
    ''';

    final script = html.ScriptElement()..text = jsCode;
    // ignore: undefined_prefixed_name
    html.document.head!.append(script);
    script.remove();
  }

  // ============ Native Printing (PDF via `printing` package) ============

  Future<void> _printPdfNative(List<int> pdfBytes) async {
    await Printing.layoutPdf(
      onLayout: (PdfPageFormat format) async => Uint8List.fromList(pdfBytes),
      name: 'Receipt',
      usePrinterSettings: true, // Let user pick printer via OS dialog
    );
  }

  // ============ PDF Receipt Generation ============

  /// Build a PDF receipt as bytes. Can be printed directly or saved.
  Future<List<int>> buildReceiptPdf({
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
    PaperSize paperSize = PaperSize.thermal80,
  }) async {
    // Determine page dimensions based on paper size
    PdfPageFormat pageFormat;
    switch (paperSize) {
      case PaperSize.thermal80:
        pageFormat = PdfPageFormat(80 * PdfPageFormat.mm, double.infinity,
            marginAll: 4 * PdfPageFormat.mm);
        break;
      case PaperSize.thermal58:
        pageFormat = PdfPageFormat(58 * PdfPageFormat.mm, double.infinity,
            marginAll: 3 * PdfPageFormat.mm);
        break;
      case PaperSize.regular:
        pageFormat = PdfPageFormat.a4.copyWith(
            marginLeft: 15 * PdfPageFormat.mm,
            marginRight: 15 * PdfPageFormat.mm,
            marginTop: 15 * PdfPageFormat.mm,
            marginBottom: 15 * PdfPageFormat.mm);
        break;
    }

    final pdf = pw.Document();
    final font = await PdfGoogleFonts.notoSansRegular();
    final fontBold = await PdfGoogleFonts.notoSansBold();
    final fontMono = await PdfGoogleFonts.notoSansMonoRegular();

    pdf.addPage(
      pw.MultiPage(
        pageFormat: pageFormat,
        build: (context) => [
          // Business Name
          pw.Center(
            child: pw.Text(businessName,
                style: pw.TextStyle(font: fontBold, fontSize: 16),
                textAlign: pw.TextAlign.center),
          ),
          pw.SizedBox(height: 2),
          pw.Center(
            child: pw.Text('Receipt',
                style: pw.TextStyle(font: font, fontSize: 10, color: PdfColors.grey600),
                textAlign: pw.TextAlign.center),
          ),
          pw.SizedBox(height: 8),

          // Invoice Number
          pw.Center(
            child: pw.Container(
              padding: pw.EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: pw.BoxDecoration(
                color: PdfColors.grey100,
                borderRadius: pw.BorderRadius.circular(3),
              ),
              child: pw.Text('$invoicePrefix$invoiceNumber',
                  style: pw.TextStyle(font: fontBold, fontSize: 11)),
            ),
          ),
          pw.SizedBox(height: 8),

          // Date & Customer
          pw.Center(
            child: pw.Text(dateTime,
                style: pw.TextStyle(font: font, fontSize: 9, color: PdfColors.grey600)),
          ),
          pw.SizedBox(height: 2),
          pw.Center(
            child: pw.Text('Customer: $customerName',
                style: pw.TextStyle(font: font, fontSize: 9, color: PdfColors.grey600)),
          ),
          pw.SizedBox(height: 8),

          // Divider
          pw.Divider(color: PdfColors.grey300, height: 1),
          pw.SizedBox(height: 6),

          // Items header
          pw.Row(children: [
            pw.Expanded(flex: 4, child: pw.Text('Item', style: pw.TextStyle(font: fontBold, fontSize: 9))),
            pw.Expanded(flex: 1, child: pw.Text('Qty', style: pw.TextStyle(font: fontBold, fontSize: 9), textAlign: pw.TextAlign.center)),
            pw.Expanded(flex: 2, child: pw.Text('Price', style: pw.TextStyle(font: fontBold, fontSize: 9), textAlign: pw.TextAlign.right)),
            pw.Expanded(flex: 2, child: pw.Text('Total', style: pw.TextStyle(font: fontBold, fontSize: 9), textAlign: pw.TextAlign.right)),
          ]),
          pw.SizedBox(height: 4),

          // Items
          ...items.map((item) => pw.Column(children: [
            pw.Row(children: [
              pw.Expanded(flex: 4, child: pw.Text(item['name'] ?? '', style: pw.TextStyle(font: font, fontSize: 9), maxLines: 1, overflow: pw.TextOverflow.clip)),
              pw.Expanded(flex: 1, child: pw.Text('${item['quantity']}', style: pw.TextStyle(font: font, fontSize: 9), textAlign: pw.TextAlign.center)),
              pw.Expanded(flex: 2, child: pw.Text('$currencySymbol ${item['unit_price']}', style: pw.TextStyle(font: font, fontSize: 9), textAlign: pw.TextAlign.right)),
              pw.Expanded(flex: 2, child: pw.Text('$currencySymbol ${item['line_total']}', style: pw.TextStyle(font: fontBold, fontSize: 9), textAlign: pw.TextAlign.right)),
            ]),
            if (item['discount'] != null && item['discount'] != '0.000')
              pw.Row(children: [
                pw.Expanded(flex: 4, child: pw.Text('  Discount', style: pw.TextStyle(font: font, fontSize: 8, color: PdfColors.red400))),
                pw.Expanded(flex: 1, child: pw.SizedBox()),
                pw.Expanded(flex: 2, child: pw.SizedBox()),
                pw.Expanded(flex: 2, child: pw.Text('- $currencySymbol ${item['discount']}', style: pw.TextStyle(font: font, fontSize: 8, color: PdfColors.red400), textAlign: pw.TextAlign.right)),
              ]),
            pw.SizedBox(height: 2),
          ])),

          pw.SizedBox(height: 6),
          pw.Divider(color: PdfColors.grey300, height: 1),
          pw.SizedBox(height: 4),

          // Subtotal
          _buildPdfTotalRow('Subtotal', '$currencySymbol $subtotal', font, fontBold),

          // Tax
          if (tax != null && tax != '0.000')
            _buildPdfTotalRow('Tax', '$currencySymbol $tax', font, fontBold),

          // Discount
          if (discount != null && discount != '0.000')
            _buildPdfTotalRow('Discount', '- $currencySymbol $discount', font, fontBold, isNegative: true),

          pw.SizedBox(height: 4),
          pw.Container(height: 2, color: PdfColors.black),
          pw.SizedBox(height: 4),

          // Grand Total
          pw.Row(mainAxisAlignment: pw.MainAxisAlignment.spaceBetween, children: [
            pw.Text('TOTAL', style: pw.TextStyle(font: fontBold, fontSize: 14)),
            pw.Text('$currencySymbol $grandTotal', style: pw.TextStyle(font: fontBold, fontSize: 14)),
          ]),

          pw.SizedBox(height: 8),

          // Payment box
          pw.Container(
            padding: pw.EdgeInsets.all(6),
            decoration: pw.BoxDecoration(
              color: PdfColors.grey50,
              borderRadius: pw.BorderRadius.circular(3),
            ),
            child: pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
              pw.Text('Payment', style: pw.TextStyle(font: fontBold, fontSize: 9)),
              pw.SizedBox(height: 3),
              ...payments.map((p) => pw.Row(
                mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                children: [
                  pw.Text('${p['method']}', style: pw.TextStyle(font: font, fontSize: 9)),
                  pw.Text('$currencySymbol ${p['amount']}', style: pw.TextStyle(font: font, fontSize: 9)),
                ],
              )),
              if (change != null && change != '0.000')
                pw.Row(
                  mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                  children: [
                    pw.Text('Change', style: pw.TextStyle(font: font, fontSize: 9)),
                    pw.Text('$currencySymbol $change', style: pw.TextStyle(font: fontBold, fontSize: 9)),
                  ],
                ),
            ]),
          ),

          pw.SizedBox(height: 10),
          pw.Divider(color: PdfColors.grey300, height: 1),
          pw.SizedBox(height: 8),

          // Footer
          pw.Center(
            child: pw.Text(footer,
                style: pw.TextStyle(font: font, fontSize: 9, color: PdfColors.grey600),
                textAlign: pw.TextAlign.center),
          ),

          pw.SizedBox(height: 8),

          // Barcode
          pw.Center(
            child: pw.BarcodeWidget(
              barcode: pw.Barcode.code128(),
              data: invoiceNumber,
              width: 150,
              height: 35,
              drawText: false,
            ),
          ),
          pw.SizedBox(height: 2),
          pw.Center(
            child: pw.Text(invoiceNumber,
                style: pw.TextStyle(font: fontMono, fontSize: 7, color: PdfColors.grey500)),
          ),
        ],
      ),
    );

    return pdf.save();
  }

  pw.Widget _buildPdfTotalRow(String label, String value, pw.Font font, pw.Font fontBold, {bool isNegative = false}) {
    return pw.Padding(
      padding: pw.EdgeInsets.symmetric(vertical: 1),
      child: pw.Row(mainAxisAlignment: pw.MainAxisAlignment.spaceBetween, children: [
        pw.Text(label, style: pw.TextStyle(font: font, fontSize: 10)),
        pw.Text(value, style: pw.TextStyle(
          font: font,
          fontSize: 10,
          color: isNegative ? PdfColors.red : PdfColors.black,
        )),
      ]),
    );
  }

  // ============ HTML Receipt (for Web) ============

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

  String _buildPrintDocument(String receiptHtml, PaperSize paperSize) {
    String pageCss;
    switch (paperSize) {
      case PaperSize.thermal80:
        pageCss = '''
          @page { size: 80mm auto; margin: 2mm 4mm; }
          .receipt { width: 72mm; font-family: "Courier New", Courier, monospace; font-size: 11px; }
          .business-name { font-size: 14px; font-weight: bold; }
          .items { font-size: 10px; }
          .col-item { width: 40%; } .col-qty { width: 12%; } .col-price { width: 22%; } .col-total { width: 26%; }
        ''';
        break;
      case PaperSize.thermal58:
        pageCss = '''
          @page { size: 58mm auto; margin: 1mm 3mm; }
          .receipt { width: 52mm; font-family: "Courier New", Courier, monospace; font-size: 9px; }
          .business-name { font-size: 12px; font-weight: bold; }
          .items { font-size: 8px; }
          .col-item { width: 38%; } .col-qty { width: 12%; } .col-price { width: 22%; } .col-total { width: 28%; }
        ''';
        break;
      case PaperSize.regular:
        pageCss = '''
          @page { size: A4; margin: 15mm; }
          .receipt { width: 100%; max-width: 80mm; margin: 0 auto; font-family: "Courier New", Courier, monospace; font-size: 12px; }
          .business-name { font-size: 20px; font-weight: bold; }
          .items { font-size: 11px; }
          .col-item { width: 40%; } .col-qty { width: 12%; } .col-price { width: 22%; } .col-total { width: 26%; }
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
    .receipt { background: white; padding: 12px; margin: 0 auto; }
    @media screen { body { display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; padding: 20px; } .receipt { box-shadow: 0 2px 8px rgba(0,0,0,0.15); border-radius: 4px; max-width: 400px; } }
    @media print { body { background: white; padding: 0; } }
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
    .center { text-align: center; } .right { text-align: right; } .bold { font-weight: bold; }
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
</body>
</html>''';
  }
}

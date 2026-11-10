import 'package:flutter/foundation.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import 'package:shared_preferences/shared_preferences.dart';

// Conditional import: on web use dart:html implementation, on native use stub
import 'print_service_stub.dart'
    if (dart.library.html) 'print_service_web.dart' as platform;

/// Paper size presets for thermal printers
enum PaperSize {
  /// Standard A4/Letter for regular printers
  regular,

  /// 80mm thermal receipt paper (most common POS thermal printer)
  thermal80,

  /// 58mm thermal receipt paper (compact POS thermal printer)
  thermal58,
}

/// Cross-platform print service.
///
/// - **Web**: opens browser print dialog with styled HTML receipt
/// - **Windows/macOS/Linux/Android/iOS**: generates PDF and opens system print dialog
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
    String? zatcaQr,
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
        zatcaQr: zatcaQr,
      );
      platform.openPrintWindow(htmlContent);
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
        zatcaQr: zatcaQr,
      );
      await Printing.layoutPdf(
        onLayout: (PdfPageFormat format) async => Uint8List.fromList(pdfBytes),
        name: 'Receipt',
        usePrinterSettings: true,
      );
    }
  }

  /// Print a custom text slip (e.g., EOD staff slip or report)
  Future<void> printSlip({required String title, required String content}) async {
    if (kIsWeb) {
      final html = '''
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>$title</title>
  <style>
    @page { size: 80mm auto; margin: 0; }
    body { font-family: monospace; font-size: 12px; padding: 10px; margin: 0; white-space: pre-wrap; line-height: 1.4; color: #000; background: #fff; }
  </style>
</head>
<body>$content</body>
<script>
  window.onload = function() { window.print(); };
</script>
</html>
''';
      platform.openPrintWindow(html);
    } else {
      final doc = pw.Document();
      doc.addPage(
        pw.Page(
          pageFormat: const PdfPageFormat(80 * PdfPageFormat.mm, double.infinity, marginAll: 5 * PdfPageFormat.mm),
          build: (ctx) => pw.Text(content, style: const pw.TextStyle(fontSize: 9)),
        ),
      );
      final bytes = await doc.save();
      await Printing.layoutPdf(
        onLayout: (_) async => bytes,
        name: title,
        usePrinterSettings: true,
      );
    }
  }

  // ============ PDF Receipt Generation (Native) ============

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
    String? zatcaQr,
  }) async {
    PdfPageFormat pageFormat;
    switch (paperSize) {
      case PaperSize.thermal80:
        pageFormat = PdfPageFormat(80 * PdfPageFormat.mm, 200 * PdfPageFormat.mm,
            marginAll: 4 * PdfPageFormat.mm);
        break;
      case PaperSize.thermal58:
        pageFormat = PdfPageFormat(58 * PdfPageFormat.mm, 200 * PdfPageFormat.mm,
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
    
    pw.Font font;
    pw.Font fontBold;
    pw.Font fontMono;

    try {
      font = await PdfGoogleFonts.notoSansRegular();
      fontBold = await PdfGoogleFonts.notoSansBold();
      fontMono = await PdfGoogleFonts.notoSansMonoRegular();
    } catch (_) {
      // Offline fallback: Use built-in standard PDF fonts when offline
      font = pw.Font.helvetica();
      fontBold = pw.Font.helveticaBold();
      fontMono = pw.Font.courier();
    }

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

          _buildPdfTotalRow('Subtotal', '$currencySymbol $subtotal', font),
          if (tax != null && tax != '0.000')
            _buildPdfTotalRow('Tax', '$currencySymbol $tax', font),
          if (discount != null && discount != '0.000')
            _buildPdfTotalRow('Discount', '- $currencySymbol $discount', font, isNegative: true),

          pw.SizedBox(height: 4),
          pw.Container(height: 2, color: PdfColors.black),
          pw.SizedBox(height: 4),

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

          pw.Center(
            child: pw.Text(footer,
                style: pw.TextStyle(font: font, fontSize: 9, color: PdfColors.grey600),
                textAlign: pw.TextAlign.center),
          ),

          pw.SizedBox(height: 8),

          pw.Center(
            child: (zatcaQr != null && zatcaQr.isNotEmpty)
                ? pw.Column(
                    children: [
                      pw.BarcodeWidget(
                        barcode: pw.Barcode.qrCode(),
                        data: zatcaQr,
                        width: 85,
                        height: 85,
                      ),
                      pw.SizedBox(height: 3),
                      pw.Text('ZATCA / Tax E-Invoice',
                          style: pw.TextStyle(font: font, fontSize: 7, color: PdfColors.grey600)),
                    ],
                  )
                : pw.Column(
                    children: [
                      pw.BarcodeWidget(
                        barcode: pw.Barcode.code128(),
                        data: invoiceNumber,
                        width: 150,
                        height: 35,
                        drawText: false,
                      ),
                      pw.SizedBox(height: 2),
                      pw.Text(invoiceNumber,
                          style: pw.TextStyle(font: fontMono, fontSize: 7, color: PdfColors.grey500)),
                    ],
                  ),
          ),
        ],
      ),
    );

    return pdf.save();
  }

  pw.Widget _buildPdfTotalRow(String label, String value, pw.Font font, {bool isNegative = false}) {
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
    String? zatcaQr,
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
        <td></td><td></td>
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
    ${(zatcaQr != null && zatcaQr.isNotEmpty)
      ? '<img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${Uri.encodeComponent(zatcaQr)}" alt="ZATCA QR" /><div class="barcode-text">ZATCA / Tax E-Invoice</div>'
      : '<img src="https://barcodeapi.org/api/code128/$invoiceNumber" alt="barcode" /><div class="barcode-text">$invoiceNumber</div>'}
  </div>
</div>''');

    return buffer.toString();
  }
}

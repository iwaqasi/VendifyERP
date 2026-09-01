// ignore: avoid_web_libraries_in_flutter
import 'dart:html' as html;

/// Opens a new browser window with the receipt HTML and triggers print dialog.
void openPrintWindow(String htmlContent) {
  final escaped = htmlContent
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

/// Opens the Web Share API dialog (mobile browsers) or does nothing on desktop.
Future<void> openShareDialog(String text, String title) async {
  try {
    await html.window.navigator.share({
      'text': text,
      'title': title,
    });
  } catch (_) {
    // Web Share API not available or user cancelled
  }
}

/// Stub implementation for non-web platforms (Windows, Android, iOS, macOS, Linux).
/// No web-specific code is used here.

void openPrintWindow(String htmlContent) {
  // No-op on native platforms — printing is handled via the `printing` package
}

void openShareDialog(String text, String title) {
  // No-op on native platforms
}

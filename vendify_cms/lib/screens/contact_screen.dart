import 'package:flutter/material.dart';
import 'package:vendify_cms/config/theme.dart';
import 'package:vendify_cms/services/api_service.dart';

class ContactScreen extends StatefulWidget {
  const ContactScreen({super.key});

  @override
  State<ContactScreen> createState() => _ContactScreenState();
}

class _ContactScreenState extends State<ContactScreen> {
  final CmsApiService _api = CmsApiService();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _messageController = TextEditingController();
  bool _isSubmitting = false;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 60, vertical: 40),
            color: CmsTheme.primary,
            child: const Row(
              children: [
                Text('CONTACT US', style: TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.bold, letterSpacing: 2)),
                Spacer(),
                Text('Home > Contact', style: TextStyle(color: Colors.white54, fontSize: 13)),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 60, vertical: 40),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Contact info
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Get in Touch', style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: CmsTheme.textPrimary)),
                      const SizedBox(height: 16),
                      const Text('We would love to hear from you. Send us a message and we will respond as soon as possible.', style: TextStyle(color: CmsTheme.textSecondary, height: 1.6)),
                      const SizedBox(height: 30),
                      _buildInfoItem(Icons.location_on, 'Address', 'Kuwait City, Kuwait'),
                      _buildInfoItem(Icons.phone, 'Phone', '+965 XXXX XXXX'),
                      _buildInfoItem(Icons.email, 'Email', 'info@sayaelegantstyle.com'),
                      _buildInfoItem(Icons.access_time, 'Hours', 'Sun-Thu: 10AM - 8PM'),
                    ],
                  ),
                ),
                const SizedBox(width: 40),

                // Contact form
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.all(30),
                    decoration: BoxDecoration(
                      color: CmsTheme.surface,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Send a Message', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: CmsTheme.textPrimary)),
                        const SizedBox(height: 20),
                        TextField(controller: _nameController, decoration: const InputDecoration(labelText: 'Your Name', border: OutlineInputBorder())),
                        const SizedBox(height: 16),
                        TextField(controller: _emailController, decoration: const InputDecoration(labelText: 'Email Address', border: OutlineInputBorder())),
                        const SizedBox(height: 16),
                        TextField(controller: _messageController, maxLines: 5, decoration: const InputDecoration(labelText: 'Message', border: OutlineInputBorder(), alignLabelWithHint: true)),
                        const SizedBox(height: 20),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: _isSubmitting ? null : _submitForm,
                            style: ElevatedButton.styleFrom(backgroundColor: CmsTheme.highlight, foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(vertical: 16)),
                            child: _isSubmitting ? const CircularProgressIndicator(color: Colors.white) : const Text('SEND MESSAGE', style: TextStyle(letterSpacing: 1)),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoItem(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Row(
        children: [
          Icon(icon, color: CmsTheme.highlight, size: 20),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: const TextStyle(fontSize: 12, color: CmsTheme.textMuted)),
              Text(value, style: const TextStyle(fontSize: 14, color: CmsTheme.textPrimary)),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _submitForm() async {
    if (_nameController.text.isEmpty || _emailController.text.isEmpty || _messageController.text.isEmpty) return;
    setState(() => _isSubmitting = true);
    try {
      await _api.submitContact(name: _nameController.text, email: _emailController.text, message: _messageController.text);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Message sent successfully!'), backgroundColor: CmsTheme.success));
        _nameController.clear();
        _emailController.clear();
        _messageController.clear();
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to send message'), backgroundColor: CmsTheme.error));
    }
    setState(() => _isSubmitting = false);
  }
}

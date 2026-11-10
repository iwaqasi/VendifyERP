import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/offline_transaction.dart';
import 'package:vendify_pos/providers/sync_provider.dart';

class SyncCenterDialog extends ConsumerStatefulWidget {
  const SyncCenterDialog({super.key});

  static Future<void> show(BuildContext context) {
    return showDialog(
      context: context,
      barrierDismissible: true,
      builder: (_) => const SyncCenterDialog(),
    );
  }

  @override
  ConsumerState<SyncCenterDialog> createState() => _SyncCenterDialogState();
}

class _SyncCenterDialogState extends ConsumerState<SyncCenterDialog> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(syncProvider.notifier).refreshQueue();
    });
  }

  void _showTransactionDetails(OfflineTransaction tx) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppTheme.surface,
        title: Text('Sale Details: ${tx.localId}', style: const TextStyle(fontSize: 16, color: AppTheme.textPrimary)),
        content: SizedBox(
          width: 420,
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                _buildDetailRow('Created:', DateFormat('yyyy-MM-dd HH:mm:ss').format(tx.createdAt)),
                _buildDetailRow('Status:', tx.status.toUpperCase()),
                _buildDetailRow('Subtotal:', 'KD ${tx.subtotal.toStringAsFixed(3)}'),
                _buildDetailRow('Tax:', 'KD ${tx.tax.toStringAsFixed(3)}'),
                _buildDetailRow('Discount:', 'KD ${tx.discount.toStringAsFixed(3)}'),
                _buildDetailRow('Grand Total:', 'KD ${tx.grandTotal.toStringAsFixed(3)}', isBold: true),
                const Divider(height: 24, color: AppTheme.border),
                const Text('Items Breakdown:', style: TextStyle(fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                const SizedBox(height: 8),
                ...tx.products.map((p) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Row(
                    children: [
                      Text('${p['quantity']}x ', style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold)),
                      Expanded(child: Text('Product #${p['product_id']} (Var #${p['variation_id']})', style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13))),
                      Text('KD ${(p['unit_price'] as num? ?? 0).toStringAsFixed(3)}', style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13)),
                    ],
                  ),
                )),
                const Divider(height: 24, color: AppTheme.border),
                const Text('Payments:', style: TextStyle(fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                const SizedBox(height: 8),
                ...tx.payments.map((p) => Text('• ${p['method']?.toString().toUpperCase()}: KD ${(p['amount'] as num? ?? 0).toStringAsFixed(3)}', style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13))),
                if (tx.errorMessage != null && tx.errorMessage!.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: AppTheme.error.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(color: AppTheme.error.withValues(alpha: 0.3)),
                    ),
                    child: Text('Error Reason: ${tx.errorMessage}', style: const TextStyle(color: AppTheme.error, fontSize: 12)),
                  ),
                ],
              ],
            ),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Close')),
        ],
      ),
    );
  }

  Widget _buildDetailRow(String label, String value, {bool isBold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
          Text(value, style: TextStyle(color: AppTheme.textPrimary, fontSize: 13, fontWeight: isBold ? FontWeight.bold : FontWeight.normal)),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final syncState = ref.watch(syncProvider);
    final notifier = ref.read(syncProvider.notifier);
    final queue = syncState.queue;

    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Container(
        width: 720,
        height: 600,
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppTheme.primary.withValues(alpha: 0.1),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.sync_alt, color: AppTheme.primary, size: 24),
                ),
                const SizedBox(width: 12),
                const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Sync Center & Offline Ledger',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                    ),
                    Text(
                      'Manage pending offline transactions and catalog synchronization',
                      style: TextStyle(fontSize: 12, color: AppTheme.textSecondary),
                    ),
                  ],
                ),
                const Spacer(),
                // Connection status chip
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: syncState.isOnline
                        ? AppTheme.success.withValues(alpha: 0.15)
                        : Colors.orange.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: syncState.isOnline ? AppTheme.success : Colors.orange,
                      width: 1,
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 8,
                        height: 8,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: syncState.isOnline ? AppTheme.success : Colors.orange,
                        ),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        syncState.isOnline ? 'Online' : 'Offline',
                        style: TextStyle(
                          color: syncState.isOnline ? AppTheme.success : Colors.orange,
                          fontWeight: FontWeight.bold,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                IconButton(
                  icon: const Icon(Icons.close, color: AppTheme.textMuted),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // Metrics Cards Row
            Row(
              children: [
                Expanded(
                  child: _buildMetricCard(
                    title: 'Pending Sales',
                    value: '${syncState.pendingCount}',
                    icon: Icons.hourglass_top,
                    color: syncState.pendingCount > 0 ? Colors.amber.shade700 : AppTheme.success,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _buildMetricCard(
                    title: 'Sync Failures',
                    value: '${syncState.failedCount}',
                    icon: Icons.error_outline,
                    color: syncState.failedCount > 0 ? AppTheme.error : AppTheme.textMuted,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _buildMetricCard(
                    title: 'Last Catalog Sync',
                    value: syncState.lastSyncTime != null
                        ? DateFormat('HH:mm - dd/MM').format(syncState.lastSyncTime!)
                        : 'Never',
                    icon: Icons.inventory_2_outlined,
                    color: AppTheme.primary,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),

            // Action Bar
            Row(
              children: [
                ElevatedButton.icon(
                  onPressed: (!syncState.isOnline || syncState.status == SyncStatus.syncing || syncState.pendingCount == 0)
                      ? null
                      : () => notifier.syncAll(),
                  icon: syncState.status == SyncStatus.syncing
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black))
                      : const Icon(Icons.cloud_upload_outlined, size: 18),
                  label: Text(
                    syncState.status == SyncStatus.syncing ? 'Syncing Queue...' : 'Sync All Pending',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    foregroundColor: Colors.black,
                  ),
                ),
                const SizedBox(width: 8),
                OutlinedButton.icon(
                  onPressed: syncState.status == SyncStatus.syncing ? null : () => notifier.syncCatalogOnly(),
                  icon: const Icon(Icons.refresh, size: 16),
                  label: const Text('Refresh Catalog'),
                  style: OutlinedButton.styleFrom(foregroundColor: AppTheme.textPrimary),
                ),
                const Spacer(),
                IconButton(
                  icon: const Icon(Icons.replay, color: AppTheme.textSecondary),
                  tooltip: 'Reload Queue',
                  onPressed: () => notifier.refreshQueue(),
                ),
              ],
            ),
            const SizedBox(height: 12),
            const Divider(color: AppTheme.border),

            // Queue List
            Expanded(
              child: queue.isEmpty
                  ? Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.check_circle_outline, size: 56, color: AppTheme.success.withValues(alpha: 0.7)),
                          const SizedBox(height: 12),
                          const Text(
                            'Offline Queue is Empty',
                            style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                          ),
                          const SizedBox(height: 4),
                          const Text(
                            'All sales are synced with the central ERP database.',
                            style: TextStyle(fontSize: 13, color: AppTheme.textSecondary),
                          ),
                        ],
                      ),
                    )
                  : ListView.separated(
                      itemCount: queue.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (ctx, index) {
                        final tx = queue[index];
                        return _buildQueueItem(tx, notifier);
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMetricCard({
    required String title,
    required String value,
    required IconData icon,
    required Color color,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppTheme.surfaceLight,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppTheme.border),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(icon, color: color, size: 20),
          ),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(fontSize: 11, color: AppTheme.textMuted)),
              const SizedBox(height: 2),
              Text(value, style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: color)),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildQueueItem(OfflineTransaction tx, SyncNotifier notifier) {
    Color statusColor;
    String statusLabel;
    switch (tx.status) {
      case 'syncing':
        statusColor = Colors.blue;
        statusLabel = 'Syncing...';
        break;
      case 'failed':
        statusColor = AppTheme.error;
        statusLabel = 'Failed';
        break;
      case 'synced':
        statusColor = AppTheme.success;
        statusLabel = 'Synced';
        break;
      case 'pending':
      default:
        statusColor = Colors.amber.shade700;
        statusLabel = 'Pending';
        break;
    }

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppTheme.surfaceLight,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: tx.status == 'failed' ? AppTheme.error.withValues(alpha: 0.3) : AppTheme.border,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                tx.localId,
                style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.textPrimary, fontSize: 13),
              ),
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  statusLabel,
                  style: TextStyle(color: statusColor, fontSize: 11, fontWeight: FontWeight.bold),
                ),
              ),
              if (tx.retryCount > 0) ...[
                const SizedBox(width: 6),
                Text('(${tx.retryCount} retries)', style: const TextStyle(color: AppTheme.textMuted, fontSize: 11)),
              ],
              const Spacer(),
              Text(
                'KD ${tx.grandTotal.toStringAsFixed(3)}',
                style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: AppTheme.primary),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              Text(
                '${tx.products.length} item(s) • ${DateFormat('HH:mm - dd/MM').format(tx.createdAt)}',
                style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
              ),
              const Spacer(),
              TextButton(
                onPressed: () => _showTransactionDetails(tx),
                child: const Text('View Breakdown', style: TextStyle(fontSize: 12)),
              ),
              const SizedBox(width: 4),
              if (tx.status == 'failed' || tx.status == 'pending')
                ElevatedButton(
                  onPressed: () => notifier.retrySingle(tx),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    foregroundColor: Colors.black,
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    minimumSize: Size.zero,
                  ),
                  child: const Text('Retry', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                ),
              const SizedBox(width: 4),
              IconButton(
                icon: const Icon(Icons.delete_outline, size: 18, color: AppTheme.error),
                tooltip: 'Discard',
                onPressed: () => _confirmDelete(tx.localId, notifier),
              ),
            ],
          ),
          if (tx.errorMessage != null && tx.errorMessage!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: AppTheme.error.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(4),
              ),
              child: Text(
                tx.errorMessage!,
                style: const TextStyle(color: AppTheme.error, fontSize: 11),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ],
      ),
    );
  }

  void _confirmDelete(String localId, SyncNotifier notifier) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppTheme.surface,
        title: const Text('Discard Offline Sale?', style: TextStyle(color: AppTheme.textPrimary)),
        content: const Text(
          'This will remove this transaction permanently from the offline queue. This action cannot be undone.',
          style: TextStyle(color: AppTheme.textSecondary),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(ctx);
              notifier.deleteSingle(localId);
            },
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.error, foregroundColor: Colors.white),
            child: const Text('Discard'),
          ),
        ],
      ),
    );
  }
}


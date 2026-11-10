import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/providers/sync_provider.dart';
import 'package:vendify_pos/screens/pos/widgets/sync_center_dialog.dart';

class SyncStatusBadge extends ConsumerWidget {
  final bool isCompact;

  const SyncStatusBadge({
    super.key,
    this.isCompact = false,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final syncState = ref.watch(syncProvider);

    Color badgeColor;
    IconData icon;
    String label;

    if (syncState.status == SyncStatus.syncing) {
      badgeColor = Colors.blue;
      icon = Icons.sync;
      label = 'Syncing...';
    } else if (syncState.failedCount > 0) {
      badgeColor = AppTheme.error;
      icon = Icons.warning_amber_rounded;
      label = '${syncState.failedCount} Failed';
    } else if (syncState.pendingCount > 0) {
      badgeColor = Colors.amber.shade700;
      icon = Icons.cloud_queue;
      label = '${syncState.pendingCount} Pending';
    } else if (!syncState.isOnline) {
      badgeColor = Colors.orange;
      icon = Icons.wifi_off;
      label = 'Offline';
    } else {
      badgeColor = AppTheme.success;
      icon = Icons.wifi;
      label = 'Online';
    }

    return InkWell(
      onTap: () => SyncCenterDialog.show(context),
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: EdgeInsets.symmetric(
          horizontal: isCompact ? 8 : 10,
          vertical: isCompact ? 3 : 5,
        ),
        decoration: BoxDecoration(
          color: badgeColor.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: badgeColor.withValues(alpha: 0.4), width: 1),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (syncState.status == SyncStatus.syncing)
              SizedBox(
                width: 12,
                height: 12,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: badgeColor,
                ),
              )
            else
              Icon(icon, size: 14, color: badgeColor),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                color: badgeColor,
                fontSize: 11,
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
        ),
      ),
    );
  }
}


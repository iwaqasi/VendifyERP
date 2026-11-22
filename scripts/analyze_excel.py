import openpyxl
from collections import Counter

file_path = 'C:/Users/vikki/.gemini/antigravity/brain/4a887ffd-665f-45bc-814f-c4e421cbb075/.user_uploaded/media_1795288194139_2f22c85b.xlsx'
wb = openpyxl.load_workbook(file_path, data_only=True)

print("Sheet names:", wb.sheetnames)

# 1. DCS Details
sheet_dcs = wb['DCS Details']
dcs_rows = list(sheet_dcs.iter_rows(values_only=True))
print(f"DCS rows: {len(dcs_rows)}")
dcs_header = dcs_rows[0]
print("DCS Header:", dcs_header)
# Sample DCS rows
for r in dcs_rows[1:10]:
    print("DCS sample:", r)

# 2. Vendors
sheet_vendors = wb['Vendors']
vendor_rows = list(sheet_vendors.iter_rows(values_only=True))
print(f"\nVendors rows: {len(vendor_rows)}")
vendor_header = vendor_rows[0]
print("Vendor Header:", vendor_header)
for r in vendor_rows[1:10]:
    print("Vendor sample:", r)

# 3. Customers
sheet_cust = wb['Customers']
cust_rows = list(sheet_cust.iter_rows(values_only=True))
print(f"\nCustomers rows: {len(cust_rows)}")
cust_header = cust_rows[0]
print("Cust Header:", cust_header)
for r in cust_rows[1:10]:
    print("Cust sample:", r)

# 4. Inventory
sheet_inv = wb['Inventory']
inv_rows = list(sheet_inv.iter_rows(values_only=True))
print(f"\nInventory rows: {len(inv_rows)}")
inv_header = inv_rows[0]
print("Inventory Header:", inv_header)
for r in inv_rows[1:10]:
    print("Inv sample:", r)

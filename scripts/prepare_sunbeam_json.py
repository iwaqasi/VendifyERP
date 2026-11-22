import openpyxl
import json
import os
import re

file_path = 'C:/Users/vikki/.gemini/antigravity/brain/4a887ffd-665f-45bc-814f-c4e421cbb075/.user_uploaded/media_1795288194139_2f22c85b.xlsx'
output_dir = 'd:/Projects/Laravel/UltimatePOS7.0/storage/sunbeam_import'
os.makedirs(output_dir, exist_ok=True)

print("Loading workbook...")
wb = openpyxl.load_workbook(file_path, data_only=True)

# 1. DCS Details (Categories)
print("Processing DCS Details...")
sheet_dcs = wb['DCS Details']
dcs_rows = list(sheet_dcs.iter_rows(values_only=True))[1:]
categories = []
seen_codes = set()
for r in dcs_rows:
    code = str(r[0]).strip() if r[0] else ''
    name = str(r[4]).strip() if r[4] else ''
    dept_name = str(r[5]).strip() if r[5] else ''
    class_name = str(r[6]).strip() if r[6] else ''
    subclass_name = str(r[7]).strip() if r[7] else ''
    if not code and not name:
        continue
    # Clean multiple spaces inside name
    name_clean = re.sub(r'\s+', ' ', name) if name else code
    if code and code not in seen_codes:
        categories.append({
            'code': code,
            'name': name_clean,
            'dept_name': dept_name,
            'class_name': class_name,
            'subclass_name': subclass_name
        })
        seen_codes.add(code)

with open(f'{output_dir}/categories.json', 'w', encoding='utf-8') as f:
    json.dump(categories, f, ensure_ascii=False, indent=2)
print(f"Saved {len(categories)} categories.")

# 2. Vendors (Suppliers)
print("Processing Vendors...")
sheet_vendors = wb['Vendors']
vendor_rows = list(sheet_vendors.iter_rows(values_only=True))[1:]
vendors = []
for r in vendor_rows:
    code = r[0]
    company = str(r[1]).strip() if r[1] else ''
    first = str(r[2]).strip() if r[2] else ''
    last = str(r[3]).strip() if r[3] else ''
    phone1 = str(r[4]).strip() if r[4] else ''
    phone2 = str(r[5]).strip() if r[5] else ''
    if code is None and not company:
        continue
    vendors.append({
        'vend_code': code,
        'company': company,
        'first_name': first,
        'last_name': last,
        'phone1': phone1,
        'phone2': phone2
    })

with open(f'{output_dir}/vendors.json', 'w', encoding='utf-8') as f:
    json.dump(vendors, f, ensure_ascii=False, indent=2)
print(f"Saved {len(vendors)} vendors.")

# 3. Customers
print("Processing Customers...")
sheet_cust = wb['Customers']
cust_rows = list(sheet_cust.iter_rows(values_only=True))[1:]
customers = []
for r in cust_rows:
    cust_id = r[0]
    company = str(r[1]).strip() if r[1] else ''
    first = str(r[2]).strip() if r[2] else ''
    last = str(r[3]).strip() if r[3] else ''
    phone = str(r[4]).strip() if r[4] else ''
    phone1 = str(r[5]).strip() if r[5] else ''
    phone2 = str(r[6]).strip() if r[6] else ''
    info = str(r[7]).strip() if r[7] else ''
    chrg_bal = float(r[8]) if r[8] is not None and str(r[8]).strip() != '' else 0.0
    store_credit = float(r[9]) if r[9] is not None and str(r[9]).strip() != '' else 0.0
    chrg_limit = float(r[10]) if r[10] is not None and str(r[10]).strip() != '' else 0.0

    if cust_id is None and not company and not first:
        continue
    customers.append({
        'cust_id': cust_id,
        'company': company,
        'first_name': first,
        'last_name': last,
        'phone': phone,
        'phone1': phone1,
        'phone2': phone2,
        'info': info,
        'chrg_bal': chrg_bal,
        'store_credit': store_credit,
        'chrg_limit': chrg_limit
    })

with open(f'{output_dir}/customers.json', 'w', encoding='utf-8') as f:
    json.dump(customers, f, ensure_ascii=False, indent=2)
print(f"Saved {len(customers)} customers.")

# 4. Inventory
print("Processing Inventory...")
sheet_inv = wb['Inventory']
inv_rows = list(sheet_inv.iter_rows(values_only=True))[1:]
inventory = []
used_skus = set()

for r in inv_rows:
    item_no = r[0]
    dcs = str(r[1]).strip() if r[1] else ''
    dcs_name = str(r[2]).strip() if r[2] else ''
    vend_code = r[3]
    vend_name = str(r[4]).strip() if r[4] else ''
    alu = str(r[5]).strip() if r[5] is not None else ''
    upc = str(r[6]).strip() if r[6] is not None else ''
    desc1 = str(r[7]).strip() if r[7] else ''
    desc2 = str(r[8]).strip() if r[8] else ''
    desc3 = str(r[9]).strip() if r[9] else ''
    desc4 = str(r[10]).strip() if r[10] else ''
    attr = str(r[11]).strip() if r[11] else ''
    size = str(r[12]).strip() if r[12] else ''
    cmp_qty = float(r[13]) if r[13] is not None and str(r[13]).strip() != '' else 0.0
    qty = float(r[14]) if r[14] is not None and str(r[14]).strip() != '' else 0.0
    cost = float(r[15]) if r[15] is not None and str(r[15]).strip() != '' else 0.0
    price = float(r[16]) if r[16] is not None and str(r[16]).strip() != '' else 0.0
    arabic_cat = str(r[17]).strip() if r[17] else ''
    flag = str(r[18]).strip() if r[18] else ''
    arabic_trans = str(r[19]).strip() if r[19] else ''

    if item_no is None and not desc1 and not alu:
        continue

    # Determine unique SKU
    sku = alu if alu else f"SBH-{item_no}"
    # In case of any duplicate SKU, append suffix
    base_sku = sku
    counter = 1
    while sku in used_skus:
        sku = f"{base_sku}-{counter}"
        counter += 1
    used_skus.add(sku)

    # Determine Name
    if desc2 and desc1 and desc2.lower() != desc1.lower():
        if desc1.lower() in desc2.lower():
            name = desc2
        else:
            name = f"{desc2} ({desc1})"
    else:
        name = desc2 or desc1 or alu or f"Item #{item_no}"
    name = re.sub(r'\s+', ' ', name).strip()[:190]

    # Determine Arabic Name
    name_ar = arabic_trans or desc4 or arabic_cat or ""
    name_ar = re.sub(r'\s+', ' ', name_ar).strip()[:190]

    inventory.append({
        'item_no': item_no,
        'sku': sku,
        'upc': upc,
        'name': name,
        'name_ar': name_ar,
        'desc1': desc1,
        'desc2': desc2,
        'desc3': desc3,
        'desc4': desc4,
        'dcs': dcs,
        'dcs_name': dcs_name,
        'vend_code': vend_code,
        'vend_name': vend_name,
        'qty': qty,
        'cost': max(0.0, cost),
        'price': max(0.0, price),
        'arabic_cat': arabic_cat,
        'arabic_trans': arabic_trans
    })

with open(f'{output_dir}/inventory.json', 'w', encoding='utf-8') as f:
    json.dump(inventory, f, ensure_ascii=False, indent=2)
print(f"Saved {len(inventory)} inventory items.")

"""Convert CSV files to XLSX"""

import os
from openpyxl.utils.exceptions import IllegalCharacterError
import pandas

root = "/home/ubuntu/dump/kohesio/"
csv_files = [f for f in os.listdir(root) if f.endswith(".csv")]
for f in sorted(csv_files):
    ff = root+f
    output = f"{ff[:-3]}xlsx"
    if not os.path.isfile(output):
        print(f"Processing {f}...")
        df = pandas.read_csv(ff)
        writer = pandas.ExcelWriter(output, engine='xlsxwriter',options={'strings_to_urls': False})
        try:
            df.to_excel(writer)
            writer.close()
        except IllegalCharacterError:
            try:
                df.to_excel(output, engine='xlsxwriter')
            except:
                print("Failed!")
    else:
        print(f"Skipping {f}")

Get-ChildItem 'C:\Users\joaob\OneDrive\Desktop\documento sitema' -Filter '*.png' | Sort-Object Name | Select-Object -ExpandProperty Name

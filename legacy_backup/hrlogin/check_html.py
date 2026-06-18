with open(r'C:\Users\harsh\OneDrive\Desktop\hr_super\hrlogin\dashboard.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

stack = []
for idx, line in enumerate(lines, 1):
    # Very basic tag analysis
    temp = line.strip()
    # Check for <div
    pos = 0
    while True:
        pos_div = temp.find('<div', pos)
        pos_close = temp.find('</div>', pos)
        
        if pos_div == -1 and pos_close == -1:
            break
            
        if pos_div != -1 and (pos_close == -1 or pos_div < pos_close):
            # Found opening div
            # Extract class if any
            class_str = ""
            class_pos = temp.find('class="', pos_div)
            if class_pos != -1:
                end_class = temp.find('"', class_pos + 7)
                class_str = temp[class_pos+7:end_class]
            stack.append((idx, 'div', class_str))
            pos = pos_div + 4
        else:
            # Found closing div
            if stack:
                opened = stack.pop()
                # print(f"Closed tag from line {opened[0]} ({opened[2]}) at line {idx}")
            else:
                print(f"ERROR: Unmatched closing div at line {idx}")
            pos = pos_close + 6

print("\n--- Remaining unclosed tags in stack ---")
for idx, tag, cls in stack:
    print(f"Line {idx}: <{tag} class=\"{cls}\">")

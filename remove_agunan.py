import sys
# 1-indexed line numbers to match my view: 1866 to 2577 inclusive.
# 1866 is index 1865. 2577 is index 2576.
start_idx = 1865
end_idx = 2577 # Up to this index excluded, meaning deleting up to index 2576. Wait, if I want to delete including 2577 (the "</div>"), then I should delete up to 2577 index? Wait, line 2577 is index 2576. So lines[2577:] starts at index 2577 (which is line 2578).

with open('d:/laragon/www/andrian/bank-kredit/analis/form_umum.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

lines = lines[:1865] + lines[2577:]

with open('d:/laragon/www/andrian/bank-kredit/analis/form_umum.php', 'w', encoding='utf-8') as f:
    f.writelines(lines)

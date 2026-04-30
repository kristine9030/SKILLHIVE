#!/usr/bin/env python3
import re

with open('pages/employer/post_internship.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Use regex to match the multi-line input element and consolidate it to one line
# This pattern matches the input tag spread across multiple lines
pattern = r'<input type="checkbox" form="internshipForm" name="skills\[\]" value="<\?php echo \$sid; \?>"(\s+)style="width:18px;height:18px;accent-color:var\(--primary-light,#138b84\);cursor:pointer;"(\s+)<\?php echo \$checked \? \'checked\' : \'\'; \?> class="skill-checkbox">'

replacement = '<input type="checkbox" form="internshipForm" name="skills[]" value="<?php echo $sid; ?>" style="width:18px;height:18px;accent-color:var(--primary-light,#138b84);cursor:pointer;" <?php echo $checked ? \'checked\' : \'\'; ?> class="skill-checkbox">'

content = re.sub(pattern, replacement, content, flags=re.MULTILINE | re.DOTALL)

with open('pages/employer/post_internship.php', 'w', encoding='utf-8') as f:
    f.write(content)

print('File fixed successfully!')

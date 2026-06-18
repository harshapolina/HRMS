const fs = require('fs');
const content = fs.readFileSync('C:\\Users\\harsh\\OneDrive\\Desktop\\hr_super\\hrlogin\\dashboard.php', 'utf8');
const lines = content.split('\n');

const stack = [];
lines.forEach((line, index) => {
    const idx = index + 1;
    let temp = line.trim();
    let pos = 0;
    while (true) {
        let pos_div = temp.indexOf('<div', pos);
        let pos_close = temp.indexOf('</div>', pos);
        
        if (pos_div === -1 && pos_close === -1) {
            break;
        }
            
        if (pos_div !== -1 && (pos_close === -1 || pos_div < pos_close)) {
            let class_str = "";
            let class_pos = temp.indexOf('class="', pos_div);
            if (class_pos !== -1) {
                let end_class = temp.indexOf('"', class_pos + 7);
                class_str = temp.substring(class_pos + 7, end_class);
            }
            stack.push({ line: idx, tag: 'div', class: class_str });
            pos = pos_div + 4;
        } else {
            if (stack.length > 0) {
                stack.pop();
            } else {
                console.log(`ERROR: Unmatched closing div at line ${idx}`);
            }
            pos = pos_close + 6;
        }
    }
});

console.log("\n--- Remaining unclosed tags in stack ---");
stack.forEach(item => {
    console.log(`Line ${item.line}: <div class="${item.class}">`);
});

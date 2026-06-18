const fs = require('fs');
const content = fs.readFileSync('C:\\Users\\harsh\\OneDrive\\Desktop\\hr_super\\hrlogin\\assets\\css\\Users.css', 'utf8');

const lines = content.split('\n');
lines.forEach((line, index) => {
    if (line.includes('col-') || line.includes('row') || line.includes('g-4') || (line.includes('padding') && line.includes('!important'))) {
        console.log(`Line ${index + 1}: ${line.trim()}`);
    }
});

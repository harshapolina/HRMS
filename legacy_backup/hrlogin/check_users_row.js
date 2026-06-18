const fs = require('fs');
const content = fs.readFileSync('C:\\Users\\harsh\\OneDrive\\Desktop\\hr_super\\hrlogin\\users.php', 'utf8');

const lines = content.split('\n');
lines.forEach((line, index) => {
    if (line.includes('class="row') || line.includes('class=\'row') || line.includes('row ')) {
        console.log(`Line ${index + 1}: ${line.trim()}`);
    }
});

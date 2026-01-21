const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const srcDir = path.join(root, 'src', 'blocks');
const outDir = path.join(root, 'blocks');

const copyDir = (from, to) => {
    if (!fs.existsSync(from)) {
        return;
    }
    fs.mkdirSync(to, { recursive: true });
    fs.cpSync(from, to, { recursive: true });
};

const build = () => {
    if (!fs.existsSync(srcDir)) {
        return;
    }
    fs.mkdirSync(outDir, { recursive: true });
    const entries = fs.readdirSync(srcDir, { withFileTypes: true });
    entries.forEach((entry) => {
        if (!entry.isDirectory()) {
            return;
        }
        const from = path.join(srcDir, entry.name);
        const to = path.join(outDir, entry.name);
        copyDir(from, to);
    });
};

const watch = () => {
    build();
    fs.watch(srcDir, { recursive: true }, () => {
        build();
    });
};

if (process.argv.includes('--watch')) {
    watch();
} else {
    build();
}

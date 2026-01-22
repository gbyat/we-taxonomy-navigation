const wpPot = require('wp-pot');
const fs = require('fs');
const path = require('path');

// Check if languages directory exists, create if not
const languagesDir = path.join(__dirname, '..', 'languages');
if (!fs.existsSync(languagesDir)) {
    fs.mkdirSync(languagesDir, { recursive: true });
}

try {
    // Generate .pot file
    wpPot({
        destFile: path.join(languagesDir, 'we-taxonomy-navigation.pot'),
        domain: 'we-taxonomy-navigation',
        package: 'WE Taxonomy Navigation',
        bugReport: 'https://github.com/gbyat/we-taxonomy-navigation/issues',
        lastTranslator: 'Gabriele Laesser <gabriele@webentwicklerin.at>',
        team: 'webentwicklerin <gabriele@webentwicklerin.at>',
        src: [
            'we-taxonomy-navigation.php',
            'includes/**/*.php',
            'src/**/*.js',
            'blocks/**/*.js'
        ],
        exclude: [
            'node_modules/**',
            'languages/**',
            'scripts/**'
        ],
        headers: {
            'Report-Msgid-Bugs-To': 'https://github.com/gbyat/we-taxonomy-navigation/issues',
            'Language-Team': 'webentwicklerin <gabriele@webentwicklerin.at>'
        }
    });

    console.log('✅ .pot file generated successfully: languages/we-taxonomy-navigation.pot');
} catch (error) {
    console.error('❌ Error generating .pot file:', error);
    process.exit(1);
}

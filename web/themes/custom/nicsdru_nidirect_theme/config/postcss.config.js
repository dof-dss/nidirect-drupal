'use strict';

const postcssImport = require('postcss-import');
const autoprefixer = require('autoprefixer');
const postcssAssets = require('postcss-assets');
const prefixSelector = require('postcss-prefix-selector');

/**
 * PostCSS config that changes behavior depending on --env
 *
 * Usage:
 *   npm run css-post                   → postcss --env theme
 *   npm run build:ckeditor:css         → postcss --env ckeditor
 *   npm run build:ckeditor-layout:css  → postcss --env ckeditor-layout
 */
module.exports = (ctx) => {
  const env = ctx.env || 'theme'; // default target
  console.log(`▶ Building CSS for PostCSS target: ${env}`);

  const basePlugins = [
    postcssImport(),
    autoprefixer({
      cascade: false,
      overrideBrowserslist: ['> 0.5%, last 2 versions, not dead'],
      grid: 'autoplace'
    }),
    postcssAssets({
      cachebuster: true,
      relative: true,
      loadPaths: ['images'],
      baseUrl: '/themes/custom/nicsdru_nidirect_theme/'
    })
  ];

  if (env === 'ckeditor' || env === 'ckeditor-layout') {
    basePlugins.push(
      prefixSelector({
        prefix: '.ck .ck-content',
        transform: (prefix, selector, prefixedSelector) => {
          if (selector.startsWith('@')) return selector;
          if (selector === 'html' || selector === 'body') return prefix;
          return prefixedSelector;
        }
      })
    );
  }

  if (env === 'ckeditor-layout') {
    basePlugins.push(
      prefixSelector({
        prefix: '.layout-builder-configure-block ',
        transform: (prefix, selector, prefixedSelector) => {
          if (selector.startsWith('@')) return selector;
          if (selector === 'html' || selector === 'body') return prefix;
          return prefixedSelector;
        }
      })
    );
  }

  return {
    map: ctx.file.dirname.includes('examples')
      ? false
      : {
        inline: false,
        prev: false,
        annotation: false,
        sourcesContent: false
      },
    plugins: basePlugins
  };
};

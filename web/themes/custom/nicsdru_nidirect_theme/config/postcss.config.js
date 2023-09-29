'use strict'

module.exports = (ctx) => ({
  map: ctx.file.dirname.includes('examples') ? false : {
    inline: false,
    prev: false,
    annotation: false,
    sourcesContent: false
  },
  plugins: {
    'autoprefixer': {
      cascade: false,
      "overrideBrowserslist": [
        "> 0.5%, last 2 versions, not dead"
      ],
      grid: "autoplace"
    },
    'postcss-assets': {
      cachebuster: true,
      relative: true,
      loadPaths: ['images'],
      baseUrl: '/themes/custom/nicsdru_nidirect_theme/'
    }
  }
})

const imagemin = require('imagemin');
const PNGImages = 'src/images/**/*.png';
const JPEGImages = 'src/images/**/*.{jpg,jpeg,JPG,JPEG}';
const GIFImages = 'src/images/**/*.gif';
const output = 'images';

const imageminJpegtran = require('imagemin-jpegtran');
const imageminJpegoptim = require('imagemin-jpegoptim');
const imageminOptipng = require('imagemin-optipng');
const imageminPngcrush = require('imagemin-pngcrush');
const imageminPngout = require('imagemin-pngout');
const imageminZopfli = require('imagemin-zopfli');
const imageminGifsicle = require('imagemin-gifsicle');

imagemin([JPEGImages], output, {use: [imageminJpegtran()]}).then(() => {
  console.log('Images optimized');
});

const optimiseJPEGImages = () =>
    imagemin([JPEGImages], output, {
      plugins: [
        imageminJpegoptim(),
        imageminJpegtran(),
      ]
    });

optimiseJPEGImages()
    .catch(error => console.log(error));

const optimisePNGImages = () =>
    imagemin([PNGImages], output, {
      plugins: [
        imageminOptipng(),
        imageminPngcrush({
          reduce: true,
        }),
        imageminPngout(),
        imageminZopfli(),
      ]
    });

optimisePNGImages()
    .catch(error => console.log(error));

const optimiseGIFImages = () =>
    imagemin([GIFImages], output, {
      plugins: [
        imageminGifsicle(),
      ]
    });

optimiseJPEGImages()
    .catch(error => console.log(error));

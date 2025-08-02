<script>
  import { ClassicEditor, Markdown, Essentials, PasteFromOffice, Fullscreen, Clipboard, FindAndReplace, RemoveFormat, Heading, Paragraph, Bold, Italic, Strikethrough, BlockQuote, Code, CodeBlock, AutoLink, Link, List } from 'ckeditor5';
  import { Ckeditor } from '@ckeditor/ckeditor5-vue';
  import ar from 'ckeditor5/translations/ar.js';
  import bg from 'ckeditor5/translations/bg.js';
  import cs from 'ckeditor5/translations/cs.js';
  import da from 'ckeditor5/translations/da.js';
  import de from 'ckeditor5/translations/de.js';
  import el from 'ckeditor5/translations/el.js';
  import es from 'ckeditor5/translations/es.js';
  import et from 'ckeditor5/translations/et.js';
  import fi from 'ckeditor5/translations/fi.js';
  import fr from 'ckeditor5/translations/fr.js';
  import he from 'ckeditor5/translations/he.js';
  import hu from 'ckeditor5/translations/hu.js';
  import id from 'ckeditor5/translations/id.js';
  import it from 'ckeditor5/translations/it.js';
  import ja from 'ckeditor5/translations/ja.js';
  import ko from 'ckeditor5/translations/ko.js';
  import lt from 'ckeditor5/translations/lt.js';
  import lv from 'ckeditor5/translations/lv.js';
  import nl from 'ckeditor5/translations/nl.js';
  import no from 'ckeditor5/translations/no.js';
  import pl from 'ckeditor5/translations/pl.js';
  import pt from 'ckeditor5/translations/pt.js';
  import ro from 'ckeditor5/translations/ro.js';
  import ru from 'ckeditor5/translations/ru.js';
  import sk from 'ckeditor5/translations/sk.js';
  import sl from 'ckeditor5/translations/sl.js';
  import sv from 'ckeditor5/translations/sv.js';
  import th from 'ckeditor5/translations/th.js';
  import tr from 'ckeditor5/translations/tr.js';
  import uk from 'ckeditor5/translations/uk.js';
  import vi from 'ckeditor5/translations/vi.js';
  import zh from 'ckeditor5/translations/zh.js';
  import 'ckeditor5/ckeditor5.css';

  export default {
    components: {
      Ckeditor
    },

    props: {
      'modelValue': {type: String, default: ''},
      'config': {type: Object, default: () => {}},
      'assets': {type: Object, default: () => {}},
      'readonly': {type: Boolean, default: false},
      'context': {type: Object},
    },

    emits: ['update:modelValue', 'error'],

    data() {
      return {
        editor: ClassicEditor,
        visible: true,
      }
    },

    beforeUnmount() {
      this.visible = false // avoid CKEditor DOM issues
    },

    computed: {
      ckconfig() {
        return {
          licenseKey: 'GPL',
          plugins: [ Markdown, Essentials, PasteFromOffice, Fullscreen, Clipboard, FindAndReplace, RemoveFormat, Heading, Paragraph, Bold, Italic, Strikethrough, BlockQuote, Code, CodeBlock, AutoLink, Link, List ],
          toolbar: [ 'undo', 'redo', 'removeFormat', '|', 'heading', '|', 'bold', 'italic', 'strikethrough', 'code', 'link', '|', 'blockQuote', 'codeBlock', '|', 'bulletedList', 'numberedList', '|', 'fullscreen' ],
          translations: [ar, bg, cs, da, de, el, es, et, fi, fr, he, hu, id, it, ja, ko, lt, lv, no, nl, pl, pt, ro, ru, sk, sl, sv, th, tr, uk, vi, zh],
          language: {
            ui: this.$vuetify.locale.current
          }
        }
      }
    },

    methods: {
      update(value) {
        if(this.modelValue != value) {
          this.$emit('update:modelValue', value);
        }
      },


      async validate() {
        return await true
      }
    }
  }
</script>

<template>
  <div v-if="visible">
    <ckeditor
      :config="ckconfig"
      :editor="editor"
      :disabled="readonly"
      :modelValue="modelValue"
      @update:modelValue="update($event)"
    ></ckeditor>
  </div>
</template>

<style>
</style>
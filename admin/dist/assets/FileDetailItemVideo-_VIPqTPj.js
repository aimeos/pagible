import{Wt as e,X as t,Y as n,q as r,rt as i,wn as a}from"./charts-qBJJ9lq9.js";import{C as o,f as s,m as c}from"./stores-Bm6PX1aQ.js";import{s as l}from"./graphql-BRoDkLYC.js";import{r as u}from"./json-Dnt6XAOd.js";import{en as d,lt as f}from"./mdi-DjCnrp2D.js";import{t as p}from"./VBtn-CtGXdTrb.js";import{t as m}from"./VIcon-DDPYF8dY.js";import{a as h,z as g}from"./index-CfOwHhUR.js";import{d as _,o as v}from"./files-LNke4jow.js";var y=l`
  mutation ($id: ID!, $preview: Upload) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        id
        published
        data
        created_at
      }
    }
  }
`,b=l`
  mutation ($id: ID!, $preview: Boolean) {
    saveFile(id: $id, input: {}, preview: $preview) {
      id
      latest {
        id
        published
        data
        created_at
      }
    }
  }
`,x={props:{item:{type:Object,required:!0},readonly:{type:Boolean,default:!1}},emits:[`sync`],data(){return{loading:{}}},setup(){return{confirm:c(),messages:s(),fileurl:h,previewUrl:_,mdiTooltipImage:d,mdiImagePlus:f}},beforeUnmount(){let e=this.$refs.video;e&&(e.pause(),e.removeAttribute(`src`),e.load())},methods:{addCover(){if(this.readonly)return this.messages.denied();if(this.loading.cover)return;let e=this.$refs.video;if(!e)return this.messages.add(this.$gettext(`No video element found`),`error`);if(e.readyState<HTMLMediaElement.HAVE_CURRENT_DATA||!e.videoWidth||!e.videoHeight)return this.messages.add(this.$gettext(`Video not loaded yet`),`info`);let t=this.item.path.replace(/\.[A-Za-z0-9]+$/,`.png`).split(`/`).pop(),n=document.createElement(`canvas`),r=n.getContext(`2d`),i=this.$gettext(`Error saving video cover`);n.width=e.videoWidth,n.height=e.videoHeight,this.loading.cover=!0;try{r.drawImage(e,0,0,e.videoWidth,e.videoHeight),v(n,`image/png`,1).then(e=>{this.cover(new File([e],t,{type:`image/png`}),i)}).catch(e=>{this.loading.cover=!1,this.messages.error(i,e)}).finally(()=>{n.width=0,n.height=0})}catch(e){n.width=0,n.height=0,this.loading.cover=!1,this.messages.error(i,e)}},cover(e,t){return this.loading.cover=!0,this.$apollo.mutate({mutation:e===!1?b:y,variables:{id:this.item.id,preview:e},context:{hasUpload:e!==!1}}).then(e=>{o(`files`);let t=e.data?.saveFile?.latest;t&&this.$emit(`sync`,{previews:u(t.data)?.previews||{},latestId:t.id,published:t.published,updated_at:t.created_at})}).catch(e=>this.messages.error(t,e)).finally(()=>{this.loading.cover=!1})},async removeCover(){if(this.readonly)return this.messages.denied();if(!this.loading.cover&&await this.confirm.ask(this.$gettext(`Remove cover image`),this.$gettext(`Remove the cover image of this video?`))&&!this.loading.cover)return this.cover(!1,this.$gettext(`Error removing video cover`))},uploadCover(e){if(this.readonly)return this.messages.denied();let t=e.target.files[0];if(e.target.value=``,!this.loading.cover){if(!t)return this.messages.add(this.$gettext(`No file selected`),`error`);this.cover(t,this.$gettext(`Error uploading video cover`))}}}},S={class:`editor-container`},C=[`src`],w={key:0,class:`toolbar`},T=[`aria-label`,`title`,`disabled`],E=[`src`,`alt`],D={key:1};function O(o,s,c,l,u,d){return e(),t(`div`,S,[r(`video`,{ref:`video`,src:l.fileurl(c.item),crossorigin:`anonymous`,class:`element`,controls:``},null,8,C),c.readonly?n(``,!0):(e(),t(`div`,w,[Object.values(c.item.previews||{}).length?(e(),t(`button`,{key:0,"aria-label":o.$gettext(`Remove cover image`),title:o.$gettext(`Remove cover image`),disabled:u.loading.cover,class:`btn-cover-remove`,type:`button`,onClick:s[0]||=e=>d.removeCover()},[r(`img`,{class:`video-preview`,src:l.previewUrl(c.item),alt:c.item.name},null,8,E)],8,T)):(e(),t(`div`,D,[i(p,{icon:l.mdiTooltipImage,loading:u.loading.cover,title:o.$gettext(`Use as cover image`),class:`btn-cover-use`,onClick:s[1]||=e=>d.addCover()},null,8,[`icon`,`loading`,`title`]),i(p,{icon:``,class:`btn-cover-upload`,loading:u.loading.cover,title:o.$gettext(`Upload cover image`),onClick:s[3]||=e=>o.$refs.coverInput.click()},{default:a(()=>[i(m,{icon:l.mdiImagePlus},null,8,[`icon`]),r(`input`,{ref:`coverInput`,type:`file`,accept:`image/*`,class:`cover-input`,onChange:s[2]||=e=>d.uploadCover(e)},null,544)]),_:1},8,[`loading`,`title`])]))]))])}var k=g(x,[[`render`,O],[`__scopeId`,`data-v-0fa0786a`]]);export{k as default};
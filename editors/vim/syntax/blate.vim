" Vim syntax file -- Blate Template
" Language:    Blate  <https://github.com/silassare/blate>
" Maintainer:  silassare
" File types:  *.blate
"
" Install:
"   Copy syntax/blate.vim  -> ~/.vim/syntax/blate.vim
"   Copy ftdetect/blate.vim -> ~/.vim/ftdetect/blate.vim
"
" The file type defaults to HTML with Blate tags layered on top.
" PHP syntax inside {~ ... ~} blocks requires the $VIMRUNTIME/syntax/php.vim.

if exists("b:current_syntax")
  finish
endif

" --- Base: HTML ---------------------------------------------------------------
" Import HTML so that HTML tags, attributes, entities and strings all
" get their normal highlighting inside a .blate file.
runtime! syntax/html.vim
unlet! b:current_syntax

syntax case match

" --- Priority ordering note ---------------------------------------------------
" Vim applies the LAST-defined syntax rule when two patterns match at the same
" starting position (see :help syn-priority, rule 2c).  The order here is:
"   1. comment  {# #}   and inline-php  {~ ~}  -- unique prefixes, no conflict
"   2. block-open {@ / block-close {/ / breakpoint {: / print-raw {=
"   3. blatePrint  {   -- catch-all
"   4. blateRawBlock  {@raw}  -- LAST so it beats block-open and blatePrint at
"      the same start position

" --- {# comment #} ------------------------------------------------------------
syntax region blateComment
      \ start="{#"  end="#}"
      \ contains=NONE keepend extend
highlight default link blateComment Comment

" --- {~ inline PHP ~} ---------------------------------------------------------
" Source PHP syntax if available; fall back to plain highlighting otherwise.
if exists("g:blate_php_syntax") || filereadable($VIMRUNTIME . "/syntax/php.vim")
  syntax include @BlatePHP syntax/php.vim
  syntax region blatePhpBlock
        \ start="{~"  end="~}"
        \ contains=@BlatePHP keepend extend
else
  syntax region blatePhpBlock
        \ start="{~"  end="~}"
        \ contains=NONE keepend extend
endif
highlight default link blatePhpBlock Special

" --- {@blockname ...} ---------------------------------------------------------
syntax region blateBlockOpen
      \ start="{@"  end="}"
      \ contains=blateBlockKwd,@blateExpr keepend extend
highlight default link blateBlockOpen Statement

" --- {/blockname} -------------------------------------------------------------
syntax region blateBlockClose
      \ start="{/"  end="}"
      \ contains=blateBlockKwd keepend extend
highlight default link blateBlockClose Statement

" --- {:breakpoint ...} --------------------------------------------------------
syntax region blateBreak
      \ start="{:"  end="}"
      \ contains=blateBreakKwd,@blateExpr keepend extend
highlight default link blateBreak Statement

" --- {= expr} (raw / unescaped print) -----------------------------------------
syntax region blatePrintRaw
      \ start="{="  end="}"
      \ contains=@blateExpr keepend extend
highlight default link blatePrintRaw Identifier

" --- {expr} (escaped print, catch-all) ---------------------------------------
syntax region blatePrint
      \ start="{"   end="}"
      \ contains=@blateExpr keepend extend
highlight default link blatePrint Identifier

" --- {@raw}...{/raw} (literal content -- no inner Blate parsing) -------------
" MUST be defined AFTER all other { regions.  Vim's priority rule: when two
" patterns start at the same position, the LAST-defined rule wins.  By placing
" blateRawBlock here, it beats blateBlockOpen (start={@) and blatePrint
" (start={) at the {@ position, so {/raw} correctly acts as the end anchor.
syntax region blateRawBlock
      \ start="{@raw}"  end="{/raw}"
      \ contains=blateRawTag keepend extend
syntax match blateRawTag contained "{@raw}\|{/raw}"
highlight default link blateRawBlock Normal
highlight default link blateRawTag Statement

" === Keywords =================================================================
syntax keyword blateBlockKwd contained
      \ if each set switch repeat capture scoped slot
      \ extends import_raw import raw php
syntax keyword blateBreakKwd contained
      \ elseif else case default
highlight default link blateBlockKwd Keyword
highlight default link blateBreakKwd Keyword

" === Expression cluster =======================================================
syntax cluster blateExpr contains=
      \ blateString,blateNumber,blateConstant,
      \ blateDataCtx,blateGlobalCtx,blateHelperRef,
      \ blateKwdInAs,
      \ blateOperator,blatePipe,blateDot,
      \ blateFunc,blateVar

" Strings
syntax region blateString contained
      \ start=+'+  skip=+\\'+ end=+'+
      \ contains=blateEscape
syntax region blateString contained
      \ start=+"+  skip=+\\"+ end=+"+
      \ contains=blateEscape
syntax match blateEscape contained "\\['\"]"
highlight default link blateString String
highlight default link blateEscape SpecialChar

" Numbers  (-42  /  3.14)
syntax match blateNumber contained "-\?\b\d\+\(\.\d\+\)\?\b"
highlight default link blateNumber Number

" Language constants: null true false (any case)
syntax keyword blateConstant contained null true false NULL TRUE FALSE
highlight default link blateConstant Constant

" $$ -- raw DataContext reference
syntax match blateDataCtx contained "\$\$"
highlight default link blateDataCtx Special

" $helperName -- forced helper-layer lookup ($ prefix)
syntax match blateHelperRef contained "\$[a-zA-Z_][a-zA-Z0-9_]*"
highlight default link blateHelperRef Identifier

" $global -- global vars layer reference; defined AFTER blateHelperRef so it wins at same position
syntax match blateGlobalCtx contained "\$global\b"
highlight default link blateGlobalCtx Special

" Structural keywords: in (each), as (repeat)
syntax keyword blateKwdInAs contained in as
highlight default link blateKwdInAs Keyword

" Binary operators -- define before blatePipe so || wins over | at same pos
syntax match blateOperator contained "&&\|||"
syntax match blateOperator contained "??"
syntax match blateOperator contained "[!=]="
syntax match blateOperator contained "[<>]=\?"
syntax match blateOperator contained "[-!+*/]"
highlight default link blateOperator Operator

" | pipe filter (single pipe, not ||)
syntax match blatePipe contained "|"
highlight default link blatePipe Operator

" . property accessor
syntax match blateDot contained "\."
highlight default link blateDot Operator

" identifier( -- function / helper call
syntax match blateFunc contained "\b[a-zA-Z_][a-zA-Z0-9_]*\ze\s*("
highlight default link blateFunc Function

" Bare identifier -- variable or property name
syntax match blateVar contained "\b[a-zA-Z_][a-zA-Z0-9_]*\b"
highlight default link blateVar Identifier

let b:current_syntax = "blate"

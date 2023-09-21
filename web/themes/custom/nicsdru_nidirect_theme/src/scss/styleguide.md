# Style guide
The content of this style guide should be the canonical example for nearly every element and component for this site. This allows editors, designers, developers and other interested parties to the project to see and understand how code should look and / or work when used in the context of this site.

## Architectural Principles
CSS’ simplicity, its looseness, and its unruly nature mean that the best way of managing (read, taming) it at any reasonable scale is through a strict and specific architecture. A solid architecture can help control specificity, enforce naming conventions, manage source order, create a sane development environment, and generally make managing CSS projects a lot more consistent and comfortable.

There is no tool, no preprocessor, no magic bullet, that will make CSS better on its own: a developer’s best tool when working with such a loose syntax is self-discipline, conscientiousness, and diligence, and a well-defined architecture will help enforce and facilitate these traits.

A read through Harry Roberts [high-level advice and guidelines for writing sane, manageable, scalable CSS](https://cssguidelin.es) will guide the general principles which developers should adopt for writing code.

Drupal 8 libraries load stylesheets based on the [SMACSS](http://smacss.com) ordering of: base, layout, component, state, theme. Code in the theme is therefore structured in the same way, further details are provided at the start of each section in the guide.

